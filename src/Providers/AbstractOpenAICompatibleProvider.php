<?php

declare(strict_types=1);

namespace Kaching\Intelligence\Providers;

use Kaching\Intelligence\Contracts\LLMProviderInterface;
use Kaching\Intelligence\DTOs\LLMProviderErrorDTO;
use Kaching\Intelligence\DTOs\LLMRequestDTO;
use Kaching\Intelligence\DTOs\LLMResponseDTO;

abstract class AbstractOpenAICompatibleProvider implements LLMProviderInterface
{
    /**
     * @var callable|null Test / mock HTTP client runner: fn(string $url, array $headers, array $payload, int $timeout, int $connectTimeout): array{status: int, body: string, headers: array}
     */
    protected static $mockTransport = null;

    public function __construct(
        protected readonly string $apiKey,
        protected readonly string $baseUrl,
        protected readonly string $defaultModel,
        protected readonly array $customHeaders = []
    ) {
    }

    public static function setMockTransport(?callable $transport): void
    {
        self::$mockTransport = $transport;
    }

    public function isAvailable(): bool
    {
        return !empty(trim($this->apiKey)) && !empty(trim($this->baseUrl));
    }

    public function sendChatRequest(LLMRequestDTO $request): LLMResponseDTO
    {
        if (!$this->isAvailable()) {
            $error = new LLMProviderErrorDTO(
                errorType: LLMProviderErrorDTO::ERROR_CONFIG,
                providerIdentifier: $this->getIdentifier(),
                httpStatus: 0,
                safeMessage: "Provider [{$this->getIdentifier()}] is not configured or API key is missing."
            );
            return new LLMResponseDTO(
                success: false,
                providerIdentifier: $this->getIdentifier(),
                model: $request->model ?: $this->defaultModel,
                error: $error
            );
        }

        $endpointUrl = $this->buildEndpointUrl();
        $headers = array_merge([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ], $this->customHeaders);

        $payload = $request->toPayload();
        if (empty($payload['model'])) {
            $payload['model'] = $this->defaultModel;
        }

        try {
            $rawResult = $this->executeHttp(
                $endpointUrl,
                $headers,
                $payload,
                $request->timeoutSeconds,
                $request->connectTimeoutSeconds
            );
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            $errType = LLMProviderErrorDTO::ERROR_CONNECTION;
            $isTimeout = (str_contains(strtolower($msg), 'timeout') || str_contains(strtolower($msg), 'timed out'));
            if ($isTimeout) {
                $errType = LLMProviderErrorDTO::ERROR_TIMEOUT;
            }

            return new LLMResponseDTO(
                success: false,
                providerIdentifier: $this->getIdentifier(),
                model: (string) $payload['model'],
                content: null,
                toolCalls: [],
                usage: ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0],
                finishReason: 'error',
                error: new LLMProviderErrorDTO(
                    errorType: $errType,
                    providerIdentifier: $this->getIdentifier(),
                    httpStatus: 0,
                    safeMessage: $isTimeout ? 'Request timed out while contacting upstream LLM.' : 'Failed to connect to upstream LLM.',
                    retryAfterSeconds: null,
                    metadata: ['exception' => get_class($e), 'masked_message' => str_replace($this->apiKey, '[REDACTED]', $msg)]
                )
            );
        }

        return $this->parseResponse($rawResult, (string) $payload['model']);
    }

    protected function buildEndpointUrl(): string
    {
        $base = rtrim($this->baseUrl, '/');
        if (str_contains($base, 'completions')) {
            return $base;
        }
        if (str_contains($base, 'openai/v1')) {
            return $base . '/chat/completions';
        }
        if (str_contains($base, '/v1')) {
            return $base . '/chat/completions';
        }
        return $base . '/openai/v1/chat/completions';
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed> $payload
     * @return array{status: int, body: string, headers: array<string, string>}
     */
    protected function executeHttp(
        string $url,
        array $headers,
        array $payload,
        int $timeout,
        int $connectTimeout
    ): array {
        if (self::$mockTransport !== null) {
            return (self::$mockTransport)($url, $headers, $payload, $timeout, $connectTimeout);
        }

        $jsonPayload = json_encode($payload);
        $headerLines = [];
        foreach ($headers as $k => $v) {
            $headerLines[] = "{$k}: {$v}";
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headerLines);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connectTimeout);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

            $raw = curl_exec($ch);
            if ($raw === false) {
                $errno = curl_errno($ch);
                $errStr = curl_error($ch);
                curl_close($ch);
                return [
                    'status'  => ($errno === CURLE_OPERATION_TIMEDOUT) ? 408 : 0,
                    'body'    => $errStr,
                    'headers' => [],
                ];
            }

            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $headerText = substr($raw, 0, $headerSize);
            $body = substr($raw, $headerSize);
            curl_close($ch);

            $parsedHeaders = [];
            foreach (explode("\r\n", $headerText) as $line) {
                if (str_contains($line, ':')) {
                    [$hk, $hv] = explode(':', $line, 2);
                    $parsedHeaders[strtolower(trim($hk))] = trim($hv);
                }
            }

            return [
                'status'  => $httpCode,
                'body'    => $body,
                'headers' => $parsedHeaders,
            ];
        }

        // Fallback stream context
        $opts = [
            'http' => [
                'method'        => 'POST',
                'header'        => implode("\r\n", $headerLines),
                'content'       => $jsonPayload,
                'timeout'       => $timeout,
                'ignore_errors' => true,
            ],
        ];
        $context = stream_context_create($opts);
        $body = @file_get_contents($url, false, $context);
        $status = 0;
        $parsedHeaders = [];
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $line) {
                if (preg_match('#HTTP/[0-9\.]+\s+([0-9]+)#', $line, $matches)) {
                    $status = (int) $matches[1];
                } elseif (str_contains($line, ':')) {
                    [$hk, $hv] = explode(':', $line, 2);
                    $parsedHeaders[strtolower(trim($hk))] = trim($hv);
                }
            }
        }

        return [
            'status'  => $status,
            'body'    => $body ?: '',
            'headers' => $parsedHeaders,
        ];
    }

    /**
     * @param array{status: int, body: string, headers: array<string, string>} $raw
     */
    protected function parseResponse(array $raw, string $model): LLMResponseDTO
    {
        $status = $raw['status'];
        $body = $raw['body'];
        $headers = $raw['headers'] ?? [];

        if ($status === 0 || $status === 408) {
            $errorType = ($status === 408) ? LLMProviderErrorDTO::ERROR_TIMEOUT : LLMProviderErrorDTO::ERROR_CONNECTION;
            $error = new LLMProviderErrorDTO(
                errorType: $errorType,
                providerIdentifier: $this->getIdentifier(),
                httpStatus: $status,
                safeMessage: "Connection or timeout failure to upstream [{$this->getIdentifier()}]."
            );
            return new LLMResponseDTO(
                success: false,
                providerIdentifier: $this->getIdentifier(),
                model: $model,
                error: $error
            );
        }

        if ($status === 429) {
            $retryAfter = $this->extractRetryAfter($headers, $body);
            $error = new LLMProviderErrorDTO(
                errorType: LLMProviderErrorDTO::ERROR_RATE_LIMIT,
                providerIdentifier: $this->getIdentifier(),
                httpStatus: 429,
                safeMessage: "Rate limit exceeded on [{$this->getIdentifier()}].",
                retryAfterSeconds: $retryAfter
            );
            return new LLMResponseDTO(
                success: false,
                providerIdentifier: $this->getIdentifier(),
                model: $model,
                error: $error
            );
        }

        if ($status === 402 || ($status === 403 && (str_contains(strtolower($body), 'quota') || str_contains(strtolower($body), 'insufficient')))) {
            $error = new LLMProviderErrorDTO(
                errorType: LLMProviderErrorDTO::ERROR_QUOTA,
                providerIdentifier: $this->getIdentifier(),
                httpStatus: $status,
                safeMessage: "Quota exhausted or payment required on [{$this->getIdentifier()}]."
            );
            return new LLMResponseDTO(
                success: false,
                providerIdentifier: $this->getIdentifier(),
                model: $model,
                error: $error
            );
        }

        if ($status === 401 || $status === 403) {
            $error = new LLMProviderErrorDTO(
                errorType: LLMProviderErrorDTO::ERROR_AUTH,
                providerIdentifier: $this->getIdentifier(),
                httpStatus: $status,
                safeMessage: "Authentication or authorization failure on [{$this->getIdentifier()}]."
            );
            return new LLMResponseDTO(
                success: false,
                providerIdentifier: $this->getIdentifier(),
                model: $model,
                error: $error
            );
        }

        if ($status >= 500) {
            $error = new LLMProviderErrorDTO(
                errorType: LLMProviderErrorDTO::ERROR_SERVER,
                providerIdentifier: $this->getIdentifier(),
                httpStatus: $status,
                safeMessage: "Upstream server error on [{$this->getIdentifier()}]: {$status}."
            );
            return new LLMResponseDTO(
                success: false,
                providerIdentifier: $this->getIdentifier(),
                model: $model,
                error: $error
            );
        }

        if ($status < 200 || $status >= 300) {
            $error = new LLMProviderErrorDTO(
                errorType: LLMProviderErrorDTO::ERROR_INVALID_REQUEST,
                providerIdentifier: $this->getIdentifier(),
                httpStatus: $status,
                safeMessage: "Invalid request to [{$this->getIdentifier()}]: {$status}."
            );
            return new LLMResponseDTO(
                success: false,
                providerIdentifier: $this->getIdentifier(),
                model: $model,
                error: $error
            );
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded) || !isset($decoded['choices'][0]['message'])) {
            $error = new LLMProviderErrorDTO(
                errorType: LLMProviderErrorDTO::ERROR_MALFORMED_RESPONSE,
                providerIdentifier: $this->getIdentifier(),
                httpStatus: $status,
                safeMessage: "Malformed JSON response from [{$this->getIdentifier()}]."
            );
            return new LLMResponseDTO(
                success: false,
                providerIdentifier: $this->getIdentifier(),
                model: $model,
                error: $error,
                rawResponse: is_array($decoded) ? $decoded : []
            );
        }

        $message = $decoded['choices'][0]['message'];
        $content = isset($message['content']) ? (string) $message['content'] : null;
        $toolCalls = isset($message['tool_calls']) && is_array($message['tool_calls']) ? $message['tool_calls'] : [];
        $finishReason = isset($decoded['choices'][0]['finish_reason']) ? (string) $decoded['choices'][0]['finish_reason'] : null;
        $usage = isset($decoded['usage']) && is_array($decoded['usage']) ? $decoded['usage'] : [];

        return new LLMResponseDTO(
            success: true,
            providerIdentifier: $this->getIdentifier(),
            model: isset($decoded['model']) ? (string) $decoded['model'] : $model,
            content: $content,
            toolCalls: $toolCalls,
            finishReason: $finishReason,
            usage: $usage,
            rawResponse: $decoded
        );
    }

    protected function extractRetryAfter(array $headers, string $body): ?float
    {
        if (isset($headers['retry-after'])) {
            $val = trim($headers['retry-after']);
            if (is_numeric($val)) {
                return (float) $val;
            }
        }
        if (preg_match('/try again in ([0-9]+(?:\.[0-9]+)?)s/i', $body, $m)) {
            return (float) $m[1];
        }
        return null;
    }
}
