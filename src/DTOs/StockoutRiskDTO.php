<?php

namespace Kaching\Intelligence\DTOs;

readonly class StockoutRiskDTO
{
    public function __construct(
        public int $menuId,
        public string $menuName,
        public string $riskLevel, // 'CRITICAL', 'WARNING', 'SAFE', 'UNSUPPORTED'
        public ?float $daysRemaining,
        public array $criticalIngredients,
        public array $reasons,
        public ?int $outletId = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            menuId: (int) ($data['menu_id'] ?? 0),
            menuName: (string) ($data['menu_name'] ?? ''),
            riskLevel: (string) ($data['risk_level'] ?? 'UNSUPPORTED'),
            daysRemaining: isset($data['days_remaining']) ? (float) $data['days_remaining'] : null,
            criticalIngredients: (array) ($data['critical_ingredients'] ?? []),
            reasons: (array) ($data['reasons'] ?? []),
            outletId: isset($data['outlet_id']) ? (int) $data['outlet_id'] : null
        );
    }

    public function toArray(): array
    {
        return [
            'menu_id'              => $this->menuId,
            'menu_name'            => $this->menuName,
            'risk_level'           => $this->riskLevel,
            'days_remaining'       => $this->daysRemaining,
            'critical_ingredients' => $this->criticalIngredients,
            'reasons'              => $this->reasons,
            'outlet_id'            => $this->outletId,
        ];
    }
}
