<?php

declare(strict_types=1);

namespace Kaching\Intelligence\Contracts;

use Kaching\Intelligence\DTOs\CommodityResultDTO;

interface CommodityPriceProviderInterface
{
    public function getIdentifier(): string;

    public function isConfigured(): bool;

    /**
     * @param array{
     *     reference_date?: string,
     *     regency_city?: string,
     *     province?: string,
     *     price_level?: string
     * } $options
     */
    public function fetchPrices(array $options = []): CommodityResultDTO;
}
