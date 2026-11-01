<?php

declare(strict_types=1);

namespace Kaching\Intelligence\RawMaterial;

use Kaching\Intelligence\DTOs\CommodityReferenceDTO;
use Kaching\Intelligence\DTOs\MenuIngredientContextDTO;
use Kaching\Intelligence\DTOs\MenuRecipeContextDTO;
use Kaching\Intelligence\DTOs\RawMaterialImpactResultDTO;
use Kaching\Intelligence\DTOs\RawMaterialItemContextDTO;

class RawMaterialImpactEngine
{
    /**
     * Deterministically simulate commodity shock and HPP impact across menu recipes.
     *
     * @param array<int, RawMaterialItemContextDTO> $rawMaterials
     * @param array<int, MenuRecipeContextDTO> $recipes
     * @param array<string, CommodityReferenceDTO> $commodityPrices key: commodityCode
     * @param float $shockPercent e.g. 20.0 for +20%
     * @param string|null $filterCommodityCode optional commodity to isolate
     * @return RawMaterialImpactResultDTO
     */
    public function simulateImpact(
        array $rawMaterials,
        array $recipes,
        array $commodityPrices = [],
        float $shockPercent = 20.0,
        ?string $filterCommodityCode = null
    ): RawMaterialImpactResultDTO {
        $warnings = [];
        $materialMap = [];

        foreach ($rawMaterials as $mat) {
            $materialMap[(string) $mat->itemId] = $mat;
        }

        $evaluatedMenus = [];
        $affectedCommodities = [];

        foreach ($recipes as $menu) {
            $originalHpp = 0.0;
            $simulatedHpp = 0.0;
            $ingredientBreakdown = [];
            $hasMatchedCommodity = false;

            foreach ($menu->ingredients as $ing) {
                $matId = (string) $ing->itemId;
                $mat = $materialMap[$matId] ?? null;

                if ($mat === null) {
                    $warnings[] = "Menu [{$menu->name}] requires item #{$matId} which was not found in raw materials context.";
                    continue;
                }

                $commCode = CommodityClassifier::resolveCommodityCode($mat->name, $mat->explicitCommodityCode);
                $isTargetCommodity = false;

                if ($commCode !== null) {
                    if ($filterCommodityCode === null || $filterCommodityCode === $commCode) {
                        $isTargetCommodity = true;
                        $hasMatchedCommodity = true;
                        $affectedCommodities[$commCode] = true;
                    }
                }

                // Deterministic conversion from recipe ingredient unit to raw material base unit
                $ingUnit = $ing->uom ?? $mat->baseUom;
                $convResult = UnitDimensionNormalizer::convert(
                    qty: (float) $ing->qty,
                    sourceUnit: $ingUnit,
                    targetUnit: $mat->baseUom,
                    customConversions: $mat->packagingConversions
                );

                if (!$convResult['success']) {
                    $warnings[] = "Unit mismatch for item [{$mat->name}] in menu [{$menu->name}]: {$convResult['reason']}";
                    // Fallback to direct raw multiplication if dimensions matched or fallback needed
                    $effectiveQty = (float) $ing->qty;
                } else {
                    $effectiveQty = (float) $convResult['converted_qty'];
                }

                $currentUnitCost = (float) $mat->costPrice;
                $ingredientOriginalCost = $effectiveQty * $currentUnitCost;
                $originalHpp += $ingredientOriginalCost;

                if ($isTargetCommodity) {
                    $multiplier = 1.0 + ($shockPercent / 100.0);
                    $simulatedUnitCost = $currentUnitCost * $multiplier;
                    $ingredientSimulatedCost = $effectiveQty * $simulatedUnitCost;
                    $simulatedHpp += $ingredientSimulatedCost;
                } else {
                    $simulatedHpp += $ingredientOriginalCost;
                }

                $ingredientBreakdown[] = [
                    'item_id' => $mat->itemId,
                    'name' => $mat->name,
                    'qty' => $ing->qty,
                    'uom' => $ingUnit,
                    'effective_qty' => $effectiveQty,
                    'base_uom' => $mat->baseUom,
                    'commodity_code' => $commCode,
                    'is_shocked' => $isTargetCommodity,
                    'original_cost' => round($ingredientOriginalCost, 2),
                    'simulated_cost' => round($isTargetCommodity ? $ingredientSimulatedCost : $ingredientOriginalCost, 2),
                ];
            }

            // Fallback to recorded cost price if recipe ingredient sum is zero
            $baselineHpp = $originalHpp > 0.0 ? $originalHpp : (float) $menu->recordedCostPrice;
            $adjustedSimulatedHpp = $originalHpp > 0.0 ? $simulatedHpp : ($baselineHpp * ($hasMatchedCommodity ? (1.0 + $shockPercent / 100.0) : 1.0));

            $sellingPrice = (float) $menu->sellingPrice;
            $currentMarginRp = $sellingPrice - $baselineHpp;
            $currentMarginPct = $sellingPrice > 0.0 ? ($currentMarginRp / $sellingPrice) * 100.0 : 0.0;

            $simulatedMarginRp = $sellingPrice - $adjustedSimulatedHpp;
            $simulatedMarginPct = $sellingPrice > 0.0 ? ($simulatedMarginRp / $sellingPrice) * 100.0 : 0.0;

            $deltaHppRp = $adjustedSimulatedHpp - $baselineHpp;
            $deltaHppPct = $baselineHpp > 0.0 ? ($deltaHppRp / $baselineHpp) * 100.0 : 0.0;

            $evaluatedMenus[] = [
                'menu_id' => $menu->menuId,
                'name' => $menu->name,
                'selling_price' => round($sellingPrice, 2),
                'original_hpp' => round($baselineHpp, 2),
                'simulated_hpp' => round($adjustedSimulatedHpp, 2),
                'delta_hpp_rp' => round($deltaHppRp, 2),
                'delta_hpp_pct' => round($deltaHppPct, 2),
                'original_margin_pct' => round($currentMarginPct, 2),
                'simulated_margin_pct' => round($simulatedMarginPct, 2),
                'has_shock_applied' => $hasMatchedCommodity && abs($deltaHppRp) > 0.001,
                'ingredients' => $ingredientBreakdown,
            ];
        }

        // Generate recommendations
        $recommendations = [];
        foreach ($evaluatedMenus as $m) {
            if ($m['has_shock_applied'] && $m['delta_hpp_rp'] > 0.0) {
                $rec = [
                    'menu_id' => $m['menu_id'],
                    'menu_name' => $m['name'],
                    'urgency' => $m['simulated_margin_pct'] < 20.0 ? 'HIGH' : ($m['simulated_margin_pct'] < 40.0 ? 'MEDIUM' : 'LOW'),
                    'observed_hpp_increase' => $m['delta_hpp_rp'],
                    'margin_compression_pct' => round($m['original_margin_pct'] - $m['simulated_margin_pct'], 2),
                    'action_options' => [
                        'MAINTAIN_MARGIN' => [
                            'recommended_selling_price' => round($m['selling_price'] + $m['delta_hpp_rp'], -2), // rounded to nearest hundred
                            'note' => 'Naikkan harga jual sebesar selisih kenaikan HPP bahan.',
                        ],
                        'PORTION_TUNING' => [
                            'note' => 'Sesuaikan gramatur bahan komoditas utama tanpa mengubah harga jual.',
                        ],
                    ],
                ];
                $recommendations[] = $rec;
            }
        }

        return new RawMaterialImpactResultDTO(
            success: true,
            shockPercentApplied: $shockPercent,
            totalMenusEvaluated: count($evaluatedMenus),
            menusEvaluated: $evaluatedMenus,
            commodityMovements: array_keys($affectedCommodities),
            recommendations: $recommendations,
            diagnosticsWarnings: array_values(array_unique($warnings)),
            calculatedAt: gmdate('Y-m-d\TH:i:s\Z')
        );
    }
}
