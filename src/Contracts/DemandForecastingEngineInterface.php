<?php

namespace Kaching\Intelligence\Contracts;

use Kaching\Intelligence\DTOs\DemandForecastContextDTO;
use Kaching\Intelligence\DTOs\DemandForecastResultDTO;

interface DemandForecastingEngineInterface
{
    /**
     * Compute demand forecast for given context.
     *
     * @param DemandForecastContextDTO $context
     * @return DemandForecastResultDTO
     */
    public function forecast(DemandForecastContextDTO $context): DemandForecastResultDTO;

    /**
     * Engine unique identifier.
     *
     * @return string
     */
    public function getEngineIdentifier(): string;
}
