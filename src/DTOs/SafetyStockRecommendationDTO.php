<?php

namespace Kaching\Intelligence\DTOs;

final class SafetyStockRecommendationDTO
{
    /**
     * @param string $itemIdentifier Product/raw material identifier
     * @param string $itemName Item display name
     * @param string $uom Unit of measure
     * @param float $averageDailyDemand Input daily demand
     * @param float $leadTimeDays Input lead time
     * @param float $targetServiceLevel Target service level
     * @param float $zScore Standard normal quantile Z corresponding to service level
     * @param float $leadTimeDemand Expected demand during lead time (d * L)
     * @param float $safetyStock Calculated safety stock buffer
     * @param float $reorderPoint Calculated reorder point (d * L + safetyStock)
     * @param float|null $currentStockOnHand Current stock on hand if provided
     * @param float|null $suggestedReorderQuantity Suggested replenishment quantity
     * @param bool $isReorderRecommended Whether stock <= reorderPoint
     * @param array<string> $assumptions Explicit formula assumptions
     * @param array<string> $warnings Warnings regarding high variability or zero demand
     */
    public function __construct(
        public readonly string $itemIdentifier,
        public readonly string $itemName,
        public readonly string $uom,
        public readonly float $averageDailyDemand,
        public readonly float $leadTimeDays,
        public readonly float $targetServiceLevel,
        public readonly float $zScore,
        public readonly float $leadTimeDemand,
        public readonly float $safetyStock,
        public readonly float $reorderPoint,
        public readonly ?float $currentStockOnHand = null,
        public readonly ?float $suggestedReorderQuantity = null,
        public readonly bool $isReorderRecommended = false,
        public readonly array $assumptions = [],
        public readonly array $warnings = []
    ) {}

    public function toArray(): array
    {
        return [
            'item_identifier' => $this->itemIdentifier,
            'item_name' => $this->itemName,
            'uom' => $this->uom,
            'average_daily_demand' => $this->averageDailyDemand,
            'lead_time_days' => $this->leadTimeDays,
            'target_service_level' => $this->targetServiceLevel,
            'z_score' => $this->zScore,
            'lead_time_demand' => $this->leadTimeDemand,
            'safety_stock' => $this->safetyStock,
            'reorder_point' => $this->reorderPoint,
            'current_stock_on_hand' => $this->currentStockOnHand,
            'suggested_reorder_quantity' => $this->suggestedReorderQuantity,
            'is_reorder_recommended' => $this->isReorderRecommended,
            'assumptions' => $this->assumptions,
            'warnings' => $this->warnings,
        ];
    }
}
