<?php

declare(strict_types=1);

namespace Kaching\Intelligence\DTOs;

/**
 * Result DTO for BCG matrix and multi-criteria SAW menu portfolio analysis.
 */
final class MenuBusinessAnalyticsResultDTO
{
    /**
     * @param string $status SUCCESS, INSUFFICIENT_DATA, etc.
     * @param int $totalMenusAnalyzed
     * @param float $averageSalesVolume
     * @param float $averageProfitContribution
     * @param array<string, array{
     *     label: string,
     *     description: string,
     *     recommendation: string,
     *     count: int,
     *     items: array<array<string, mixed>>
     * }> $bcgQuadrants
     * @param array<array<string, mixed>> $rankedMenuItems Sorted by SAW final score
     * @param array<string> $strategicInsights
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly string $status,
        public readonly int $totalMenusAnalyzed,
        public readonly float $averageSalesVolume,
        public readonly float $averageProfitContribution,
        public readonly array $bcgQuadrants,
        public readonly array $rankedMenuItems,
        public readonly array $strategicInsights = [],
        public readonly array $metadata = []
    ) {
    }

    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'total_menus_analyzed' => $this->totalMenusAnalyzed,
            'average_sales_volume' => $this->averageSalesVolume,
            'average_profit_contribution' => $this->averageProfitContribution,
            'bcg_quadrants' => $this->bcgQuadrants,
            'ranked_menu_items' => $this->rankedMenuItems,
            'strategic_insights' => $this->strategicInsights,
            'metadata' => $this->metadata,
        ];
    }
}
