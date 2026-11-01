<?php

namespace Kaching\Intelligence\DemandForecast;

use InvalidArgumentException;
use Kaching\Intelligence\Contracts\SafetyStockOptimizerInterface;
use Kaching\Intelligence\DTOs\SafetyStockContextDTO;
use Kaching\Intelligence\DTOs\SafetyStockRecommendationDTO;

class SafetyStockOptimizationService implements SafetyStockOptimizerInterface
{
    /**
     * Map target service level to standard normal quantile Z.
     * Common discrete quantile mapping:
     * 0.80 -> 0.8416
     * 0.85 -> 1.0364
     * 0.90 -> 1.2816
     * 0.95 -> 1.6449
     * 0.98 -> 2.0537
     * 0.99 -> 2.3263
     * 0.999 -> 3.0902
     */
    public function getZScore(float $serviceLevel): float
    {
        if ($serviceLevel <= 0.5 || $serviceLevel >= 1.0) {
            throw new InvalidArgumentException("Service level must be between 0.5 and 1.0, {$serviceLevel} given.");
        }

        // Standard Abramowitz & Stegun rational approximation for normal quantile
        $p = 1.0 - $serviceLevel;
        $t = sqrt(-2.0 * log($p));
        
        $c0 = 2.515517;
        $c1 = 0.802853;
        $c2 = 0.010328;
        $d1 = 1.432788;
        $d2 = 0.189269;
        $d3 = 0.001308;

        $z = $t - (($c0 + $c1 * $t + $c2 * $t * $t) / (1.0 + $d1 * $t + $d2 * $t * $t + $d3 * $t * $t * $t));

        return round($z, 4);
    }

    /**
     * Compute safety stock and reorder point:
     * Lead Time Demand (LTD) = d * L
     *
     * When Lead Time is constant (sigma_L = 0):
     * SS = Z * sigma_d * sqrt(L)
     *
     * When Lead Time has variability (sigma_L > 0):
     * SS = Z * sqrt( (L * sigma_d^2) + (d^2 * sigma_L^2) )
     *
     * Reorder Point (ROP) = LTD + SS = (d * L) + SS
     *
     * Suggested Reorder Quantity:
     * If currentStock is provided: max(0, ROP - currentStock) or batch buffer
     */
    public function optimize(SafetyStockContextDTO $context): SafetyStockRecommendationDTO
    {
        $d = $context->averageDailyDemand;
        $sigmaD = $context->demandStdDev;
        $L = $context->leadTimeDays;
        $sigmaL = $context->leadTimeStdDev;
        $serviceLevel = $context->targetServiceLevel;

        $z = $this->getZScore($serviceLevel);
        $warnings = [];
        $assumptions = [
            "Normal distribution of demand during lead time assumed at service level " . ($serviceLevel * 100) . "%.",
            "Lead time is {$L} days.",
        ];

        // 1. LTD
        $leadTimeDemand = $d * $L;

        // 2. Safety Stock Formula
        if ($sigmaL > 0.0) {
            // Both demand and lead time vary
            $variance = ($L * ($sigmaD * $sigmaD)) + (($d * $d) * ($sigmaL * $sigmaL));
            $combinedStd = sqrt(max(0.0, $variance));
            $safetyStock = $z * $combinedStd;
            $assumptions[] = "Combined variance formula applied with lead time std dev = {$sigmaL}.";
        } else {
            // Constant lead time
            $safetyStock = $z * $sigmaD * sqrt($L);
            $assumptions[] = "Constant lead time assumed (lead time variance = 0).";
        }

        $safetyStock = max(0.0, round($safetyStock, 2));
        $reorderPoint = max(0.0, round($leadTimeDemand + $safetyStock, 2));

        // 3. Current Stock and Reorder Evaluation
        $currentStock = $context->currentStockOnHand;
        $suggestedReorderQty = null;
        $isReorderRecommended = false;

        if ($currentStock !== null) {
            if ($currentStock <= $reorderPoint) {
                $isReorderRecommended = true;
                // Basic suggested replenishment brings stock back to (ROP + safety buffer or min order)
                $suggestedReorderQty = max(0.0, round($reorderPoint - $currentStock + $safetyStock, 2));
            } else {
                $suggestedReorderQty = 0.0;
            }
        }

        if ($d <= 0.0001) {
            $warnings[] = 'Average daily demand is near zero. Reorder recommendations may be dormant.';
        }

        if ($sigmaD > 2.0 * $d && $d > 0.0) {
            $warnings[] = 'High demand volatility detected (standard deviation > 2x average demand). Consider intermittent demand model.';
        }

        return new SafetyStockRecommendationDTO(
            itemIdentifier: $context->itemIdentifier,
            itemName: $context->itemName,
            uom: $context->uom,
            averageDailyDemand: round($d, 2),
            leadTimeDays: round($L, 2),
            targetServiceLevel: $serviceLevel,
            zScore: $z,
            leadTimeDemand: round($leadTimeDemand, 2),
            safetyStock: $safetyStock,
            reorderPoint: $reorderPoint,
            currentStockOnHand: $currentStock !== null ? round($currentStock, 2) : null,
            suggestedReorderQuantity: $suggestedReorderQty,
            isReorderRecommended: $isReorderRecommended,
            assumptions: $assumptions,
            warnings: $warnings
        );
    }
}
