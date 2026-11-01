<?php

declare(strict_types=1);

namespace Kaching\Intelligence\DTOs;

final class RawMaterialItemContextDTO
{
    /**
     * @param array<string, float> $packagingConversions target canonical uom => multiplier (e.g. ['kg' => 0.001])
     */
    public function __construct(
        public readonly int|string $itemId,
        public readonly string $name,
        public readonly float $costPrice,
        public readonly string $baseUom,
        public readonly ?string $explicitCommodityCode = null,
        public readonly array $packagingConversions = []
    ) {
    }

    public function toArray(): array
    {
        return [
            'item_id' => $this->itemId,
            'name' => $this->name,
            'cost_price' => $this->costPrice,
            'base_uom' => $this->baseUom,
            'explicit_commodity_code' => $this->explicitCommodityCode,
            'packaging_conversions' => $this->packagingConversions,
        ];
    }
}
