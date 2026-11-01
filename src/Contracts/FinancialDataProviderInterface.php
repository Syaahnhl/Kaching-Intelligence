<?php

namespace Kaching\Intelligence\Contracts;

use Kaching\Intelligence\DTOs\FinancialMetricsDTO;

interface FinancialDataProviderInterface
{
    /**
     * Provide aggregated financial metrics without leaking raw Transaction Eloquent models.
     */
    public function getFinancialMetrics(int $userId, ?int $outletId, ?string $anchorDate = null): FinancialMetricsDTO;
}
