<?php

namespace Kaching\Intelligence\Tests\Fakes;

use Kaching\Intelligence\Contracts\FinancialDataProviderInterface;
use Kaching\Intelligence\DTOs\FinancialMetricsDTO;

class FakeFinancialDataProvider implements FinancialDataProviderInterface
{
    public function getFinancialMetrics(int $userId, ?int $outletId, ?string $anchorDate = null): FinancialMetricsDTO
    {
        return new FinancialMetricsDTO(
            revenue: 5500000.0,
            transactionCount: 42,
            averageTicket: 130952.38,
            currency: 'IDR'
        );
    }
}
