<?php

declare(strict_types=1);

namespace Kaching\Intelligence\Services;

use Kaching\Intelligence\Contracts\LLMProviderInterface;
use Kaching\Intelligence\Contracts\LLMProviderRegistryInterface;
use Kaching\Intelligence\DTOs\LLMProviderErrorDTO;
use Kaching\Intelligence\DTOs\LLMRequestDTO;
use Kaching\Intelligence\DTOs\LLMResponseDTO;

class LLMProviderOrchestrator
{
    /**
     * @var callable|null
     */
    protected $logger;

    /**
     * @param string $primaryProviderIdentifier
     * @param array<int, string> $fallbackChain Ordered list of fallback provider identifiers
     * @param callable|null $logger fn(string $level, string $message, array $context): void
     */
    public function __construct(
        protected readonly LLMProviderRegistryInterface $registry,
        protected readonly string $primaryProviderIdentifier,
        protected readonly array $fallbackChain = [],
        protected readonly int $maxRetryPerProvider = 1,
        protected readonly float $rateLimitMaxRetryBudget = 3.0,
        ?callable $logger = null
    ) {
        $this->logger = $logger;
    }

    /**
     * Execute chat request with deterministic fallback logic.
     */
    public function execute(LLMRequestDTO $request): LLMResponseDTO
    {
        $providersToTry = array_unique(array_merge(
            [$this->primaryProviderIdentifier],
            $this->fallbackChain
        ));

        $attempted = [];
        $lastError = null;

        foreach ($providersToTry as $providerId) {
            $provider = $this->registry->getProvider($providerId);
            if (!$provider instanceof LLMProviderInterface || !$provider->isAvailable()) {
                $this->log('warning', "Provider [{$providerId}] is not registered or unavailable. Skipping.");
                continue;
            }

            $attempted[] = $providerId;
            $response = $this->executeWithRetry($provider, $request);

            if ($response->success) {
                return $response;
            }

            $lastError = $response->error;

            // Decision: should we fall back?
            if (!$this->shouldFallback($lastError)) {
                $this->log('error', "Non-fallback failure on provider [{$providerId}]. Aborting chain.", [
                    'error_type' => $lastError?->errorType,
                ]);
                return $response;
            }

            $this->log('warning', "Provider [{$providerId}] failed with retryable/fallback error. Trying next in chain.", [
                'error_type' => $lastError?->errorType,
            ]);
        }

        $terminalError = new LLMProviderErrorDTO(
            errorType: LLMProviderErrorDTO::ERROR_SERVER,
            providerIdentifier: implode('->', $attempted) ?: 'none',
            httpStatus: $lastError?->httpStatus ?? 503,
            safeMessage: 'All configured LLM providers in fallback chain failed.',
            metadata: [
                'attempted_providers' => $attempted,
                'last_error'          => $lastError?->toArray(),
            ]
        );

        return new LLMResponseDTO(
            success: false,
            providerIdentifier: 'orchestrator_exhausted',
            model: $request->model,
            error: $terminalError
        );
    }

    protected function executeWithRetry(LLMProviderInterface $provider, LLMRequestDTO $request): LLMResponseDTO
    {
        $response = $provider->sendChatRequest($request);

        if (!$response->success && $response->error !== null) {
            $error = $response->error;
            if ($error->errorType === LLMProviderErrorDTO::ERROR_RATE_LIMIT) {
                $retryAfter = $error->retryAfterSeconds;
                if ($retryAfter !== null && $retryAfter <= $this->rateLimitMaxRetryBudget && $retryAfter > 0) {
                    $sleepSeconds = (int) ceil($retryAfter);
                    if ($sleepSeconds > 3) {
                        $sleepSeconds = 3;
                    }
                    if ($sleepSeconds > 0) {
                        sleep($sleepSeconds);
                    }
                    return $provider->sendChatRequest($request);
                }
            }
        }

        return $response;
    }

    protected function shouldFallback(?LLMProviderErrorDTO $error): bool
    {
        if ($error === null) {
            return false;
        }

        return in_array($error->errorType, [
            LLMProviderErrorDTO::ERROR_CONNECTION,
            LLMProviderErrorDTO::ERROR_TIMEOUT,
            LLMProviderErrorDTO::ERROR_RATE_LIMIT,
            LLMProviderErrorDTO::ERROR_SERVER,
            LLMProviderErrorDTO::ERROR_QUOTA,
            LLMProviderErrorDTO::ERROR_MALFORMED_RESPONSE,
        ], true);
    }

    protected function log(string $level, string $message, array $context = []): void
    {
        if ($this->logger !== null && is_callable($this->logger)) {
            ($this->logger)($level, $message, $context);
        }
    }
}
