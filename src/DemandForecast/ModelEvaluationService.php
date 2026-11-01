<?php

namespace Kaching\Intelligence\DemandForecast;

use DateTimeImmutable;
use InvalidArgumentException;
use Kaching\Intelligence\Contracts\DemandForecastingEngineInterface;
use Kaching\Intelligence\Contracts\ModelEvaluatorInterface;
use Kaching\Intelligence\DTOs\DemandForecastContextDTO;
use Kaching\Intelligence\DTOs\ForecastHorizonDTO;
use Kaching\Intelligence\DTOs\ModelEvaluationDTO;

class ModelEvaluationService implements ModelEvaluatorInterface
{
    /**
     * Temporal backtesting split without future data leakage.
     *
     * @param DemandForecastingEngineInterface $engine Engine to evaluate
     * @param array<array{date: string, value: float, day_of_week?: int}> $fullSeries Complete daily series
     * @param string $splitDate Cutoff boundary (history <= splitDate, test > splitDate)
     * @param int $horizonDays Step horizon (default 7)
     * @param array<string, mixed> $evalOptions Options (item_id, category, weather, etc.)
     * @return ModelEvaluationDTO
     */
    public function evaluateTemporal(
        DemandForecastingEngineInterface $engine,
        array $fullSeries,
        string $splitDate,
        int $horizonDays = 7,
        array $evalOptions = []
    ): ModelEvaluationDTO {
        if (empty($fullSeries)) {
            throw new InvalidArgumentException('Historical series cannot be empty.');
        }

        // 1. Partition into train and ground-truth test
        $trainSeries = [];
        $testActualsMap = [];

        foreach ($fullSeries as $point) {
            $date = $point['date'];
            $val = (float) ($point['value'] ?? 0.0);

            if ($date <= $splitDate) {
                $trainSeries[] = $point;
            } else {
                $testActualsMap[$date] = $val;
            }
        }

        if (empty($testActualsMap)) {
            throw new InvalidArgumentException("No observations found after split date {$splitDate}.");
        }

        // 2. Build Context for Forecast
        $splitDateTime = new DateTimeImmutable($splitDate);
        $forecastStartDate = $splitDateTime->modify('+1 day')->format('Y-m-d');
        $forecastEndDate = $splitDateTime->modify("+{$horizonDays} day")->format('Y-m-d');

        $horizon = new ForecastHorizonDTO($forecastStartDate, $forecastEndDate, $horizonDays);

        $context = new DemandForecastContextDTO(
            itemIdentifier: (string) ($evalOptions['item_id'] ?? 'EVAL_ITEM_1'),
            itemName: (string) ($evalOptions['item_name'] ?? 'Evaluated Product'),
            category: (string) ($evalOptions['category'] ?? 'General'),
            uom: (string) ($evalOptions['uom'] ?? 'pcs'),
            outletIdentifier: (string) ($evalOptions['outlet_id'] ?? 'OUTLET_1'),
            cutoffDate: $splitDate,
            horizon: $horizon,
            historicalSeries: $trainSeries,
            calendarFeatures: (array) ($evalOptions['calendar_features'] ?? []),
            weatherFeatures: (array) ($evalOptions['weather_features'] ?? []),
            contextMetadata: ['eval_mode' => 'TEMPORAL_BACKTEST']
        );

        // 3. Execute Forecast
        $result = $engine->forecast($context);

        // 4. Compute Metrics (MAE, RMSE, WAPE, Bias, Interval Coverage)
        $errors = [];
        $absErrors = [];
        $sqErrors = [];
        $sumActual = 0.0;
        $sumPred = 0.0;
        $inIntervalCount = 0;
        $evaluatedPoints = 0;

        foreach ($result->forecastPoints as $point) {
            $date = $point->date;
            if (!array_key_exists($date, $testActualsMap)) {
                continue;
            }

            $actual = $testActualsMap[$date];
            $pred = $point->predictedQuantity;

            $diff = $pred - $actual; // Error (pred - actual)
            $absDiff = abs($diff);
            $sqDiff = $diff * $diff;

            $errors[] = $diff;
            $absErrors[] = $absDiff;
            $sqErrors[] = $sqDiff;
            $sumActual += $actual;
            $sumPred += $pred;
            $evaluatedPoints++;

            if ($point->lowerBoundP10 !== null && $point->upperBoundP90 !== null) {
                if ($actual >= $point->lowerBoundP10 && $actual <= $point->upperBoundP90) {
                    $inIntervalCount++;
                }
            }
        }

        if ($evaluatedPoints === 0) {
            throw new InvalidArgumentException('Zero forecast points overlapped with test ground truth.');
        }

        $mae = array_sum($absErrors) / $evaluatedPoints;
        $rmse = sqrt(array_sum($sqErrors) / $evaluatedPoints);
        $bias = array_sum($errors) / $evaluatedPoints; // Mean Error
        
        // WAPE = sum(|pred - actual|) / sum(actual)
        // Guard against zero division
        $wape = null;
        if ($sumActual > 0.0001) {
            $wape = array_sum($absErrors) / $sumActual;
        }

        $coverage = null;
        if ($evaluatedPoints > 0 && ($result->forecastPoints[0]->lowerBoundP10 !== null)) {
            $coverage = $inIntervalCount / $evaluatedPoints;
        }

        return new ModelEvaluationDTO(
            modelIdentifier: $engine->getEngineIdentifier(),
            evaluationPeriodStart: $forecastStartDate,
            evaluationPeriodEnd: $forecastEndDate,
            sampleCount: $evaluatedPoints,
            mae: round($mae, 4),
            rmse: round($rmse, 4),
            wape: $wape !== null ? round($wape, 4) : null,
            bias: round($bias, 4),
            intervalCoverage90: $coverage !== null ? round($coverage, 4) : null,
            evaluationMetadata: [
                'train_count' => count($trainSeries),
                'test_count' => $evaluatedPoints,
                'sum_actual' => $sumActual,
                'sum_predicted' => $sumPred,
                'split_date' => $splitDate,
                'model_meta' => $result->modelMetadata->toArray(),
            ]
        );
    }
}
