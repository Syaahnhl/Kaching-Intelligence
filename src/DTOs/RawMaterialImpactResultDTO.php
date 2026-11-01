<?php

declare(strict_types=1);

namespace Kaching\Intelligence\DTOs;

final class RawMaterialImpactResultDTO
{
    /**
     * @param array<int, array> $menusEvaluated
     * @param array<int, array> $commodityMovements
     * @param array<int, array> $recommendations
     * @param array<int, string> $diagnosticsWarnings
     */
    public function __construct(
        public readonly bool $success,
        public readonly float $shockPercentApplied,
        public readonly int $totalMenusEvaluated,
        public readonly array $menusEvaluated = [],
        public readonly array $commodityMovements = [],
        public readonly array $recommendations = [],
        public readonly array $diagnosticsWarnings = [],
        public readonly string $calculatedAt = ''
    ) {
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'shock_percent_applied' => $this->shockPercentApplied,
            'total_menus_evaluated' => $this->totalMenusEvaluated,
            'menus_evaluated' => $this->menusEvaluated,
            'commodity_movements' => $this->commodityMovements,
            'recommendations' => $this->recommendations,
            'diagnostics_warnings' => $this->diagnosticsWarnings,
            'calculated_at' => $this->calculatedAt,
        ];
    }
}
