<?php

namespace Kaching\Intelligence\DemandForecast;

use DateTimeImmutable;
use InvalidArgumentException;
use Kaching\Intelligence\Contracts\DemandForecastingEngineInterface;
use Kaching\Intelligence\DTOs\DemandForecastContextDTO;
use Kaching\Intelligence\DTOs\DemandForecastResultDTO;
use Kaching\Intelligence\DTOs\ForecastPointDTO;
use Kaching\Intelligence\DTOs\ModelMetadataDTO;

class DowMedianDemandForecastService implements DemandForecastingEngineInterface
{
    public const MODEL_IDENTIFIER = 'dow_median_baseline';
    public const MODEL_VERSION = 'statistical-baseline-v1';
    public const STRATEGY = 'DOW_MEDIAN';
    public const SERVING_ORIGIN = 'INTELLIGENCE_LOCAL_ENGINE';

    protected int $maxWeeks;
    protected int $minimumRequiredDays;

    /**
     * @param int $maxWeeks Max past weeks of same day-of-week to pool for median (default 8)
     * @param int $minimumRequiredDays Minimum history days required for sufficient confidence (default 7)
     */
    public function __construct(int $maxWeeks = 8, int $minimumRequiredDays = 7)
    {
        $this->maxWeeks = max(1, $maxWeeks);
        $this->minimumRequiredDays = max(1, $minimumRequiredDays);
    }

    public function getEngineIdentifier(): string
    {
        return self::MODEL_IDENTIFIER;
    }

    public function forecast(DemandForecastContextDTO $context): DemandForecastResultDTO
    {
        $series = $context->historicalSeries;
        $historyCount = count($series);
        $warnings = [];

        // 1. Data Quality and Freshness Checks
        $allZero = true;
        foreach ($series as $point) {
            if ((float) ($point['value'] ?? 0.0) > 0.0001) {
                $allZero = false;
                break;
            }
        }

        $historyStatus = 'SUFFICIENT';
        $confidenceLevel = 'HIGH';

        if ($historyCount < $this->minimumRequiredDays) {
            $historyStatus = 'INSUFFICIENT_HISTORY';
            $confidenceLevel = 'LOW';
            $warnings[] = "Insufficient historical observations: {$historyCount} days provided (minimum {$this->minimumRequiredDays} recommended).";
        } elseif ($allZero) {
            $historyStatus = 'ZERO_DEMAND';
            $confidenceLevel = 'MEDIUM';
            $warnings[] = "All {$historyCount} historical observations have zero sales.";
        }

        // 2. Generate Continuous Horizon Points
        $startDate = new DateTimeImmutable($context->horizon->startDate);
        $points = [];
        $totalProjected = 0.0;

        for ($i = 0; $i < $context->horizon->horizonDays; $i++) {
            $currentDate = $startDate->modify("+{$i} day");
            $targetDateStr = $currentDate->format('Y-m-d');
            $targetDow = (int) $currentDate->format('N'); // 1 (Mon) - 7 (Sun)

            $predQty = $this->calculateDowMedianForDate($series, $targetDow);
            $predQty = max(0.0, round($predQty, 3));

            // Heuristic prediction intervals for baseline (CV based or 20% margin)
            $stdEstimate = 0.20 * $predQty;
            $p10 = max(0.0, round($predQty - (1.28 * $stdEstimate), 3));
            $p90 = max(0.0, round($predQty + (1.28 * $stdEstimate), 3));

            $points[] = new ForecastPointDTO(
                date: $targetDateStr,
                dayOfWeek: $targetDow,
                predictedQuantity: $predQty,
                lowerBoundP10: $p10,
                upperBoundP90: $p90,
                rawModelOutput: $predQty,
                isClipped: false,
                pointMetadata: [
                    'dow' => $targetDow,
                    'strategy' => self::STRATEGY,
                ]
            );

            $totalProjected += $predQty;
        }

        $modelMeta = new ModelMetadataDTO(
            modelIdentifier: self::MODEL_IDENTIFIER,
            modelVersion: self::MODEL_VERSION,
            strategyUsed: self::STRATEGY,
            featureVersion: 'native-statistical-dow',
            servingOrigin: self::SERVING_ORIGIN,
            fallbackApplied: false,
            provenance: [
                'max_weeks_window' => $this->maxWeeks,
                'history_days_count' => $historyCount,
            ]
        );

        return new DemandForecastResultDTO(
            itemIdentifier: $context->itemIdentifier,
            outletIdentifier: $context->outletIdentifier,
            horizon: $context->horizon,
            forecastPoints: $points,
            totalProjectedDemand: round($totalProjected, 3),
            confidenceLevel: $confidenceLevel,
            historyStatus: $historyStatus,
            modelMetadata: $modelMeta,
            dataQualityWarnings: $warnings,
            telemetry: [
                'history_count' => $historyCount,
                'all_zero' => $allZero,
            ]
        );
    }

    /**
     * Calculate median of historical sales on the same day-of-week.
     *
     * @param array<array{date: string, value: float, day_of_week?: int}> $series
     * @param int $targetDow 1 (Monday) to 7 (Sunday)
     * @return float
     */
    public function calculateDowMedianForDate(array $series, int $targetDow): float
    {
        $sameDowValues = [];
        $reversed = array_reverse($series);

        foreach ($reversed as $item) {
            $dow = isset($item['day_of_week'])
                ? (int) $item['day_of_week']
                : (int) (new DateTimeImmutable($item['date']))->format('N');

            if ($dow === $targetDow) {
                $sameDowValues[] = (float) ($item['value'] ?? 0.0);
                if (count($sameDowValues) >= $this->maxWeeks) {
                    break;
                }
            }
        }

        if (empty($sameDowValues)) {
            // Fallback to overall recent median if no observations on this DOW
            return $this->calculateOverallMedian($series);
        }

        sort($sameDowValues);
        $count = count($sameDowValues);
        $mid = intdiv($count, 2);

        if ($count % 2 === 1) {
            return (float) $sameDowValues[$mid];
        }

        return (float) (($sameDowValues[$mid - 1] + $sameDowValues[$mid]) / 2.0);
    }

    protected function calculateOverallMedian(array $series): float
    {
        if (empty($series)) {
            return 0.0;
        }

        $vals = [];
        foreach (array_slice(array_reverse($series), 0, 14) as $s) {
            $vals[] = (float) ($s['value'] ?? 0.0);
        }

        sort($vals);
        $count = count($vals);
        if ($count === 0) {
            return 0.0;
        }

        $mid = intdiv($count, 2);
        if ($count % 2 === 1) {
            return (float) $vals[$mid];
        }

        return (float) (($vals[$mid - 1] + $vals[$mid]) / 2.0);
    }
}
