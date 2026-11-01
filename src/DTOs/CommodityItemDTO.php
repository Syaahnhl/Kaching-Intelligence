<?php

declare(strict_types=1);

namespace Kaching\Intelligence\DTOs;

final class CommodityItemDTO
{
    public function __construct(
        public readonly string $commodityCode,
        public readonly string $commodityName,
        public readonly float $price,
        public readonly string $currency,
        public readonly string $rawUom,
        public readonly string $standardUom,
        public readonly float $standardPricePerUom,
        public readonly string $category,
        public readonly string $marketRegion,
        public readonly string $priceLevel,
        public readonly string $observationDate,
        public readonly string $retrievalTimestamp,
        public readonly string $sourceIdentifier,
        public readonly string $sourceAttribution,
        public readonly string $dataQuality,
        public readonly array $rawPayload = []
    ) {
    }

    public function toArray(): array
    {
        return [
            'commodity_code' => $this->commodityCode,
            'commodity_name' => $this->commodityName,
            'price' => $this->price,
            'currency' => $this->currency,
            'raw_uom' => $this->rawUom,
            'standard_uom' => $this->standardUom,
            'standard_price_per_uom' => $this->standardPricePerUom,
            'category' => $this->category,
            'market_region' => $this->marketRegion,
            'price_level' => $this->priceLevel,
            'observation_date' => $this->observationDate,
            'retrieval_timestamp' => $this->retrievalTimestamp,
            'source_identifier' => $this->sourceIdentifier,
            'source_attribution' => $this->sourceAttribution,
            'data_quality' => $this->dataQuality,
            'raw_payload' => $this->rawPayload,
        ];
    }
}
