<?php

declare(strict_types=1);

namespace Kaching\Intelligence\DTOs;

final class WeatherResultDTO
{
    public const STATUS_SUCCESS = 'SUCCESS';
    public const STATUS_EMPTY = 'EMPTY';
    public const STATUS_STALE = 'STALE';
    public const STATUS_PARTIAL = 'PARTIAL';
    public const STATUS_UNAVAILABLE = 'UNAVAILABLE';
    public const STATUS_INVALID_RESPONSE = 'INVALID_RESPONSE';
    public const STATUS_RATE_LIMIT = 'RATE_LIMIT';
    public const STATUS_TIMEOUT = 'TIMEOUT';
    public const STATUS_UNCONFIGURED = 'UNCONFIGURED';

    /**
     * @param array<int, WeatherItemDTO> $forecastItems
     */
    public function __construct(
        public readonly bool $success,
        public readonly string $status,
        public readonly string $providerIdentifier,
        public readonly string $sourceAttribution,
        public readonly ?WeatherItemDTO $current = null,
        public readonly array $forecastItems = [],
        public readonly ?string $errorMessage = null,
        public readonly ?int $httpStatus = null,
        public readonly string $retrievalTimestamp = '',
        public readonly bool $isStale = false,
        public readonly array $metadata = []
    ) {
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'status' => $this->status,
            'provider_identifier' => $this->providerIdentifier,
            'source_attribution' => $this->sourceAttribution,
            'current' => $this->current?->toArray(),
            'forecast_items' => array_map(fn (WeatherItemDTO $w) => $w->toArray(), $this->forecastItems),
            'error_message' => $this->errorMessage,
            'http_status' => $this->httpStatus,
            'retrieval_timestamp' => $this->retrievalTimestamp,
            'is_stale' => $this->isStale,
            'metadata' => $this->metadata,
        ];
    }
}
