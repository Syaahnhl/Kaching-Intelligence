<?php

namespace Kaching\Intelligence\DTOs;

use InvalidArgumentException;

final class SafetyStockContextDTO
{
    /**
     * @param string $itemIdentifier Product/raw material identifier
     * @param string $itemName Item display name
     * @param string $uom Unit of measure
     * @param float $averageDailyDemand Expected daily demand (units/day)
     * @param float $demandStdDev Standard deviation of daily demand
     * @param float $leadTimeDays Supplier lead time in days (e.g. 2.0)
     * @param float $leadTimeStdDev Standard deviation of supplier lead time (0 if constant)
     * @param float $targetServiceLevel Target non-stockout probability (e.g. 0.90, 0.95, 0.99)
     * @param float|null $currentStockOnHand Current stock on hand if provided by Core
     * @param array<string, mixed> $contextMetadata Provenance metadata
     */
    public function __construct(
        public readonly string $itemIdentifier,
        public readonly string $itemName,
        public readonly string $uom,
        public readonly float $averageDailyDemand,
        public readonly float $demandStdDev,
        public readonly float $leadTimeDays,
        public readonly float $leadTimeStdDev = 0.0,
        public readonly float $targetServiceLevel = 0.95,
        public readonly ?float $currentStockOnHand = null,
        public readonly array $contextMetadata = []
    ) {
        if ($this->averageDailyDemand < 0.0) {
            throw new InvalidArgumentException("averageDailyDemand cannot be negative, {$this->averageDailyDemand} given.");
        }
        if ($this->demandStdDev < 0.0) {
            throw new InvalidArgumentException("demandStdDev cannot be negative, {$this->demandStdDev} given.");
        }
        if ($this->leadTimeDays <= 0.0) {
            throw new InvalidArgumentException("leadTimeDays must be positive, {$this->leadTimeDays} given.");
        }
        if ($this->leadTimeStdDev < 0.0) {
            throw new InvalidArgumentException("leadTimeStdDev cannot be negative, {$this->leadTimeStdDev} given.");
        }
        if ($this->targetServiceLevel <= 0.5 || $this->targetServiceLevel >= 1.0) {
            throw new InvalidArgumentException("targetServiceLevel must be strictly between 0.5 and 1.0, {$this->targetServiceLevel} given.");
        }
    }

    public function toArray(): array
    {
        return [
            'item_identifier' => $this->itemIdentifier,
            'item_name' => $this->itemName,
            'uom' => $this->uom,
            'average_daily_demand' => $this->averageDailyDemand,
            'demand_std_dev' => $this->demandStdDev,
            'lead_time_days' => $this->leadTimeDays,
            'lead_time_std_dev' => $this->leadTimeStdDev,
            'target_service_level' => $this->targetServiceLevel,
            'current_stock_on_hand' => $this->currentStockOnHand,
            'context_metadata' => $this->contextMetadata,
        ];
    }
}
