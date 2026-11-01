<?php

namespace Kaching\Intelligence\Contracts;

use Kaching\Intelligence\DTOs\ModelEvaluationDTO;

interface ModelEvaluatorInterface
{
    /**
     * Evaluate model performance using historical backtesting with temporal split.
     *
     * @param DemandForecastingEngineInterface $engine Engine to evaluate
     * @param array<array{date: string, value: float, day_of_week?: int}> $fullSeries Complete historical daily series
     * @param string $splitDate Temporal boundary: observations <= splitDate are train/context, > splitDate are evaluation ground truth
     * @param int $horizonDays Forecast horizon evaluated per step
     * @param array<string, mixed> $evalOptions Optional configuration (e.g. weather features)
     * @return ModelEvaluationDTO
     */
    public function evaluateTemporal(
        DemandForecastingEngineInterface $engine,
        array $fullSeries,
        string $splitDate,
        int $horizonDays = 7,
        array $evalOptions = []
    ): ModelEvaluationDTO;
}
