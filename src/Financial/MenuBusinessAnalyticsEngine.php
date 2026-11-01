<?php

declare(strict_types=1);

namespace Kaching\Intelligence\Financial;

use Kaching\Intelligence\Contracts\MenuBusinessAnalyticsEngineInterface;
use Kaching\Intelligence\DTOs\MenuBusinessAnalyticsContextDTO;
use Kaching\Intelligence\DTOs\MenuBusinessAnalyticsResultDTO;

/**
 * Menu Business Analytics Engine.
 * 
 * Rules:
 * 1. Evaluates menu portfolio using standard BCG quadrant classification:
 *    - STAR: High Volume (>= avgQty) & High Profit (>= avgProfit)
 *    - CASH COW: High Volume (>= avgQty) & Low Profit (< avgProfit)
 *    - PUZZLE: Low Volume (< avgQty) & High Profit (>= avgProfit)
 *    - DOG: Low Volume (< avgQty) & Low Profit (< avgProfit)
 * 2. Multi-Criteria Simple Additive Weighting (SAW):
 *    - w1 = 0.4 (Sales Volume / Qty)
 *    - w2 = 0.4 (Total Profit Margin contribution)
 *    - w3 = 0.2 (Popularity / Order Frequency)
 * 3. 0 Eloquent/DB dependencies: pure deterministic mathematical scoring.
 */
final class MenuBusinessAnalyticsEngine implements MenuBusinessAnalyticsEngineInterface
{
    public const WEIGHT_QTY = 0.4;
    public const WEIGHT_PROFIT = 0.4;
    public const WEIGHT_POPULARITY = 0.2;

    public function analyze(MenuBusinessAnalyticsContextDTO $context): MenuBusinessAnalyticsResultDTO
    {
        $menuAggregates = $context->menuAggregates;

        if (empty($menuAggregates)) {
            return new MenuBusinessAnalyticsResultDTO(
                status: 'INSUFFICIENT_DATA',
                totalMenusAnalyzed: 0,
                averageSalesVolume: 0.0,
                averageProfitContribution: 0.0,
                bcgQuadrants: $this->initEmptyQuadrants(),
                rankedMenuItems: [],
                strategicInsights: [
                    'Tidak ada data penjualan menu pada rentang waktu yang dipilih.'
                ],
                metadata: [
                    'period' => $context->periodLabel,
                    'start_date' => $context->startDate,
                    'end_date' => $context->endDate,
                ]
            );
        }

        $totalMenus = count($menuAggregates);
        $sumQty = 0.0;
        $sumProfit = 0.0;
        $maxQty = 0.0;
        $maxProfit = 0.0;
        $maxPopularity = 0;

        $computedItems = [];

        foreach ($menuAggregates as $item) {
            $marginPerUnit = $item->currentSellingPrice - $item->costPrice;
            $totalProfit = $marginPerUnit * $item->totalQty;

            $sumQty += $item->totalQty;
            $sumProfit += $totalProfit;

            if ($item->totalQty > $maxQty) {
                $maxQty = $item->totalQty;
            }
            if ($totalProfit > $maxProfit) {
                $maxProfit = $totalProfit;
            }
            if ($item->popularity > $maxPopularity) {
                $maxPopularity = $item->popularity;
            }

            $computedItems[] = [
                'menu_id' => $item->menuId,
                'name' => $item->name,
                'category_name' => $item->category,
                'selling_price' => $item->currentSellingPrice,
                'cost_price' => $item->costPrice,
                'margin_per_unit' => $marginPerUnit,
                'qty' => $item->totalQty,
                'profit' => $totalProfit,
                'popularity' => $item->popularity,
            ];
        }

        $avgQty = $totalMenus > 0 ? $sumQty / $totalMenus : 0.0;
        $avgProfit = $totalMenus > 0 ? $sumProfit / $totalMenus : 0.0;

        $bcgQuadrants = $this->initEmptyQuadrants();
        $rankedItems = [];

        foreach ($computedItems as $row) {
            $isHighQty = $row['qty'] >= $avgQty;
            $isHighProfit = $row['profit'] >= $avgProfit;

            if ($isHighQty && $isHighProfit) {
                $category = 'STAR';
                $action = 'Menu Juara! Pertahankan ketersediaan stok, konsistensi rasa, dan kualitas layanan.';
            } elseif ($isHighQty && !$isHighProfit) {
                $category = 'CASH_COW';
                $action = 'Volume tinggi penopang kas operasional. Tingkatkan margin via paket bundling atau optimasi bahan.';
            } elseif (!$isHighQty && $isHighProfit) {
                $category = 'PUZZLE';
                $action = 'Margin besar namun kurang laku. Genjot promosi visual, tempatkan di posisi katalog strategis.';
            } else {
                $category = 'DOG';
                $action = 'Peminat rendah dan margin tipis. Pertimbangkan revisi formula resep, reposisi, atau eliminasi menu.';
            }

            // SAW Normalization
            $normQty = $maxQty > 0 ? $row['qty'] / $maxQty : 0.0;
            $normProfit = $maxProfit > 0 ? $row['profit'] / $maxProfit : 0.0;
            $normPop = $maxPopularity > 0 ? $row['popularity'] / $maxPopularity : 0.0;

            $finalScore = ($normQty * self::WEIGHT_QTY) +
                          ($normProfit * self::WEIGHT_PROFIT) +
                          ($normPop * self::WEIGHT_POPULARITY);

            $formattedItem = [
                'menu_id' => $row['menu_id'],
                'name' => $row['name'],
                'category' => $category,
                'score' => round($finalScore, 4),
                'qty' => $row['qty'],
                'profit' => round($row['profit'], 2),
                'popularity' => $row['popularity'],
                'action' => $action,
                'selling_price' => $row['selling_price'],
                'cost_price' => $row['cost_price'],
            ];

            $bcgQuadrants[$category]['items'][] = $formattedItem;
            $bcgQuadrants[$category]['count']++;
            $rankedItems[] = $formattedItem;
        }

        // Sort ranked items by score descending
        usort($rankedItems, fn ($a, $b) => $b['score'] <=> $a['score']);

        $strategicInsights = [];
        $starCount = $bcgQuadrants['STAR']['count'];
        $dogCount = $bcgQuadrants['DOG']['count'];

        if ($starCount > 0) {
            $strategicInsights[] = "Terdapat {$starCount} menu berstatus STAR yang menjadi penggerak profit dan volume penjualan.";
        }
        if ($dogCount > 0) {
            $strategicInsights[] = "Terdapat {$dogCount} menu berstatus DOG yang perlu ditinjau ulang agar tidak membebani persediaan bahan.";
        }

        return new MenuBusinessAnalyticsResultDTO(
            status: 'SUCCESS',
            totalMenusAnalyzed: $totalMenus,
            averageSalesVolume: round($avgQty, 2),
            averageProfitContribution: round($avgProfit, 2),
            bcgQuadrants: $bcgQuadrants,
            rankedMenuItems: $rankedItems,
            strategicInsights: $strategicInsights,
            metadata: [
                'period' => $context->periodLabel,
                'start_date' => $context->startDate,
                'end_date' => $context->endDate,
                'max_qty' => $maxQty,
                'max_profit' => round($maxProfit, 2),
                'max_popularity' => $maxPopularity,
            ]
        );
    }

    private function initEmptyQuadrants(): array
    {
        return [
            'STAR' => [
                'label' => 'STAR',
                'description' => 'Penjualan Tinggi • Margin Tinggi',
                'recommendation' => 'Menu Juara! Pertahankan ketersediaan stok, konsistensi rasa, dan kualitas layanan.',
                'badge_class' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                'card_border' => 'border-emerald-200',
                'items' => [],
                'count' => 0,
            ],
            'PUZZLE' => [
                'label' => 'PUZZLE',
                'description' => 'Penjualan Rendah • Margin Tinggi',
                'recommendation' => 'Margin besar namun kurang laku. Genjot promosi visual, tempatkan di posisi katalog strategis.',
                'badge_class' => 'bg-amber-50 text-amber-700 border-amber-200',
                'card_border' => 'border-amber-200',
                'items' => [],
                'count' => 0,
            ],
            'CASH_COW' => [
                'label' => 'CASH COW',
                'description' => 'Penjualan Tinggi • Margin Rendah',
                'recommendation' => 'Volume tinggi penopang kas operasional. Tingkatkan margin via paket bundling atau optimasi bahan.',
                'badge_class' => 'bg-blue-50 text-blue-700 border-blue-200',
                'card_border' => 'border-blue-200',
                'items' => [],
                'count' => 0,
            ],
            'DOG' => [
                'label' => 'DOG',
                'description' => 'Penjualan Rendah • Margin Rendah',
                'recommendation' => 'Peminat rendah dan margin tipis. Pertimbangkan revisi formula resep, reposisi, atau eliminasi menu.',
                'badge_class' => 'bg-rose-50 text-rose-700 border-rose-200',
                'card_border' => 'border-rose-200',
                'items' => [],
                'count' => 0,
            ],
        ];
    }
}
