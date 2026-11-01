<?php

declare(strict_types=1);

namespace Kaching\Intelligence\DTOs;

/**
 * Result DTO containing deterministic cashflow forecast projection and data quality status.
 */
final class ForecastResultDTO
{
    /**
     * @param string $status SUCCESS, INSUFFICIENT_HISTORY, SPARSE_DATA, ZERO_BASELINE, etc.
     * @param string $model DAY_OF_WEEK_SEASONAL_BASELINE, WEIGHTED_MOVING_AVERAGE_14, or NONE
     * @param string $dataQualityState SUFFICIENT, MODERATE_HISTORY, INSUFFICIENT_HISTORY, SPARSE_DATA, ZERO_BASELINE
     * @param int $horizonDays
     * @param float $projectedTotalInflow
     * @param float $projectedTotalOutflow
     * @param float $projectedNetOperatingCashFlow
     * @param array<array{date: string, day_of_week: string, projected_inflow: float, projected_outflow: float, projected_net_operating_cash_flow: float}> $dailyForecast
     * @param array<string, mixed> $qualityDetails
     * @param array<string> $recommendations
     * @param string|null $message Optional advisory or explanatory message
     */
    public function __construct(
        public readonly string $status,
        public readonly string $model,
        public readonly string $dataQualityState,
        public readonly int $horizonDays,
        public readonly float $projectedTotalInflow,
        public readonly float $projectedTotalOutflow,
        public readonly float $projectedNetOperatingCashFlow,
        public readonly array $dailyForecast,
        public readonly array $qualityDetails,
        public readonly array $recommendations = [],
        public readonly ?string $message = null
    ) {
    }

    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'model' => $this->model,
            'data_quality' => $this->dataQualityState,
            'horizon_days' => $this->horizonDays,
            'projected_total_inflow' => $this->projectedTotalInflow,
            'projected_total_outflow' => $this->projectedTotalOutflow,
            'projected_net_operating_cash_flow' => $this->projectedNetOperatingCashFlow,
            'daily_forecast' => $this->dailyForecast,
            'data_quality_details' => $this->qualityDetails,
            'recommendations' => $this->recommendations,
            'message' => $this->message,
        ];
    }
}
