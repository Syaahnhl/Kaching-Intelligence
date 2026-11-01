<?php

declare(strict_types=1);

namespace Kaching\Intelligence\Contracts;

use Kaching\Intelligence\DTOs\CashFlowForecastContextDTO;
use Kaching\Intelligence\DTOs\ForecastResultDTO;

/**
 * Interface for deterministic cashflow forecasting.
 * Pure reasoning & analytical calculation — zero database queries or mutations.
 */
interface CashFlowForecastingEngineInterface
{
    /**
     * Compute cashflow forecast based on verified historical aggregates.
     *
     * @param CashFlowForecastContextDTO $context
     * @return ForecastResultDTO
     */
    public function forecast(CashFlowForecastContextDTO $context): ForecastResultDTO;
}
