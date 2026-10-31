<?php

namespace Kaching\Intelligence\DTOs;

readonly class InventorySnapshotDTO
{
    public function __construct(
        public int $itemId,
        public string $itemName,
        public string $sku,
        public string $unit,
        public float $currentStock,
        public float $minimumStock,
        public float $safetyStock,
        public ?float $costPrice = null,
        public ?int $outletId = null,
        public ?string $category = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            itemId: (int) ($data['item_id'] ?? 0),
            itemName: (string) ($data['item_name'] ?? ''),
            sku: (string) ($data['sku'] ?? ''),
            unit: (string) ($data['unit'] ?? 'pcs'),
            currentStock: (float) ($data['current_stock'] ?? 0.0),
            minimumStock: (float) ($data['minimum_stock'] ?? 0.0),
            safetyStock: (float) ($data['safety_stock'] ?? 0.0),
            costPrice: isset($data['cost_price']) ? (float) $data['cost_price'] : null,
            outletId: isset($data['outlet_id']) ? (int) $data['outlet_id'] : null,
            category: isset($data['category']) ? (string) $data['category'] : null
        );
    }

    public function toArray(): array
    {
        return [
            'item_id'       => $this->itemId,
            'item_name'     => $this->itemName,
            'sku'           => $this->sku,
            'unit'          => $this->unit,
            'current_stock' => $this->currentStock,
            'minimum_stock' => $this->minimumStock,
            'safety_stock'  => $this->safetyStock,
            'cost_price'    => $this->costPrice,
            'outlet_id'     => $this->outletId,
            'category'      => $this->category,
        ];
    }
}
