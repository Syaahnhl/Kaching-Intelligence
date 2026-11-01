<?php

namespace Kaching\Intelligence\DTOs;

use InvalidArgumentException;

final class DemandForecastContextDTO
{
    /**
     * @param string $itemIdentifier Anonymized/hashed product or ingredient ID (e.g. 'MENU_42', 'ITEM_105')
     * @param string $itemName Non-sensitive display name
     * @param string $category Product category
     * @param string $uom Unit of measure (e.g. 'porsi', 'cup', 'gram')
     * @param string $outletIdentifier Authorized outlet identifier (e.g. 'OUTLET_1')
     * @param string $cutoffDate Cutoff date (history <= cutoffDate) YYYY-MM-DD
     * @param ForecastHorizonDTO $horizon Forecasting target horizon
     * @param array<array{date: string, value: float, day_of_week?: int}> $historicalSeries Daily sales observations up to cutoffDate
     * @param array<string, mixed> $calendarFeatures Explicit calendar attributes
     * @param array<string, mixed> $weatherFeatures Optional weather indicators (temperature, precip, etc.)
     * @param array<string, mixed> $contextMetadata Provenance or freshness flags
     */
    public function __construct(
        public readonly string $itemIdentifier,
        public readonly string $itemName,
        public readonly string $category,
        public readonly string $uom,
        public readonly string $outletIdentifier,
        public readonly string $cutoffDate,
        public readonly ForecastHorizonDTO $horizon,
        public readonly array $historicalSeries,
        public readonly array $calendarFeatures = [],
        public readonly array $weatherFeatures = [],
        public readonly array $contextMetadata = []
    ) {
        if (empty(trim($this->itemIdentifier))) {
            throw new InvalidArgumentException('itemIdentifier cannot be empty.');
        }
        if (empty(trim($this->outletIdentifier))) {
            throw new InvalidArgumentException('outletIdentifier cannot be empty.');
        }
        if (empty(trim($this->cutoffDate))) {
            throw new InvalidArgumentException('cutoffDate cannot be empty.');
        }
    }

    public function toArray(): array
    {
        return [
            'item_identifier' => $this->itemIdentifier,
            'item_name' => $this->itemName,
            'category' => $this->category,
            'uom' => $this->uom,
            'outlet_identifier' => $this->outletIdentifier,
            'cutoff_date' => $this->cutoffDate,
            'horizon' => $this->horizon->toArray(),
            'history_points_count' => count($this->historicalSeries),
            'calendar_features' => $this->calendarFeatures,
            'weather_features' => $this->weatherFeatures,
            'context_metadata' => $this->contextMetadata,
        ];
    }
}
