<?php

namespace Kaching\Intelligence\DTOs;

readonly class RestockRecommendationDTO
{
    public function __construct(
        public int $itemId,
        public string $itemName,
        public float $currentStock,
        public float $recommendedQty,
        public string $unit,
        public ?float $estimatedCost,
        public string $priority, // 'URGENT', 'HIGH', 'MEDIUM', 'LOW'
        public ?int $supplierId = null,
        public ?string $supplierName = null,
        public ?string $supplierPhone = null,
        public array $reasons = [],
        public ?int $outletId = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            itemId: (int) ($data['item_id'] ?? 0),
            itemName: (string) ($data['item_name'] ?? ''),
            currentStock: (float) ($data['current_stock'] ?? 0.0),
            recommendedQty: (float) ($data['recommended_qty'] ?? 0.0),
            unit: (string) ($data['unit'] ?? 'pcs'),
            estimatedCost: isset($data['estimated_cost']) ? (float) $data['estimated_cost'] : null,
            priority: (string) ($data['priority'] ?? 'MEDIUM'),
            supplierId: isset($data['supplier_id']) ? (int) $data['supplier_id'] : null,
            supplierName: isset($data['supplier_name']) ? (string) $data['supplier_name'] : null,
            supplierPhone: isset($data['supplier_phone']) ? (string) $data['supplier_phone'] : null,
            reasons: (array) ($data['reasons'] ?? []),
            outletId: isset($data['outlet_id']) ? (int) $data['outlet_id'] : null
        );
    }

    public function toArray(): array
    {
        return [
            'item_id'         => $this->itemId,
            'item_name'       => $this->itemName,
            'current_stock'   => $this->currentStock,
            'recommended_qty' => $this->recommendedQty,
            'unit'            => $this->unit,
            'estimated_cost'  => $this->estimatedCost,
            'priority'        => $this->priority,
            'supplier_id'     => $this->supplierId,
            'supplier_name'   => $this->supplierName,
            'supplier_phone'  => $this->supplierPhone,
            'reasons'         => $this->reasons,
            'outlet_id'       => $this->outletId,
        ];
    }
}
