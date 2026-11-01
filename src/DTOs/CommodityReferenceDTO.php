<?php

declare(strict_types=1);

namespace Kaching\Intelligence\DTOs;

final class CommodityReferenceDTO
{
    public function __construct(
        public readonly string $commodityCode,
        public readonly string $commodityName,
        public readonly float $currentPrice,
        public readonly string $standardUom = 'kg',
        public readonly ?float $price7d = null,
        public readonly ?string $referenceDate = null,
        public readonly string $source = 'BAPANAS'
    ) {
    }

    public function toArray(): array
    {
        return [
            'commodity_code' => $this->commodityCode,
            'commodity_name' => $this->commodityName,
            'current_price' => $this->currentPrice,
            'standard_uom' => $this->standardUom,
            'price_7d' => $this->price7d,
            'reference_date' => $this->referenceDate,
            'source' => $this->source,
        ];
    }
}
