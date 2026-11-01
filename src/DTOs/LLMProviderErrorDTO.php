<?php

declare(strict_types=1);

namespace Kaching\Intelligence\DTOs;

final class LLMProviderErrorDTO
{
    public const ERROR_CONNECTION = 'CONNECTION_FAILED';
    public const ERROR_TIMEOUT = 'TIMEOUT';
    public const ERROR_RATE_LIMIT = 'RATE_LIMIT';
    public const ERROR_AUTH = 'AUTH_FAILED';
    public const ERROR_QUOTA = 'QUOTA_EXHAUSTED';
    public const ERROR_SERVER = 'SERVER_ERROR';
    public const ERROR_INVALID_REQUEST = 'INVALID_REQUEST';
    public const ERROR_MALFORMED_RESPONSE = 'MALFORMED_RESPONSE';
    public const ERROR_CONFIG = 'INVALID_CONFIG';
    public const ERROR_UNKNOWN = 'UNKNOWN_ERROR';

    public function __construct(
        public readonly string $errorType,
        public readonly string $providerIdentifier,
        public readonly int $httpStatus,
        public readonly string $safeMessage,
        public readonly ?float $retryAfterSeconds = null,
        public readonly array $metadata = []
    ) {
    }

    public function isRetryable(): bool
    {
        return in_array($this->errorType, [
            self::ERROR_RATE_LIMIT,
            self::ERROR_TIMEOUT,
            self::ERROR_SERVER,
            self::ERROR_CONNECTION,
        ], true);
    }

    public function toArray(): array
    {
        return [
            'error_type'          => $this->errorType,
            'provider'            => $this->providerIdentifier,
            'http_status'         => $this->httpStatus,
            'safe_message'        => $this->safeMessage,
            'retry_after_seconds' => $this->retryAfterSeconds,
            'metadata'            => $this->metadata,
        ];
    }
}
