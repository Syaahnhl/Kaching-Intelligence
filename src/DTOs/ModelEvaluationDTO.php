<?php

namespace Kaching\Intelligence\DTOs;

final class ModelEvaluationDTO
{
    /**
     * @param string $modelIdentifier Unique model identifier tested
     * @param string $evaluationPeriodStart YYYY-MM-DD
     * @param string $evaluationPeriodEnd YYYY-MM-DD
     * @param int $sampleCount Total test observations / evaluated points
     * @param float $mae Mean Absolute Error
     * @param float $rmse Root Mean Squared Error
     * @param float|null $wape Weighted Absolute Percentage Error (null if zero denominator)
     * @param float $bias Mean Forecast Bias (Mean Error = sum(pred - actual) / n)
     * @param float|null $intervalCoverage90 Percentage of actuals within p10-p90 (0.0 to 1.0)
     * @param array<string, mixed> $evaluationMetadata Additional telemetry
     */
    public function __construct(
        public readonly string $modelIdentifier,
        public readonly string $evaluationPeriodStart,
        public readonly string $evaluationPeriodEnd,
        public readonly int $sampleCount,
        public readonly float $mae,
        public readonly float $rmse,
        public readonly ?float $wape = null,
        public readonly float $bias = 0.0,
        public readonly ?float $intervalCoverage90 = null,
        public readonly array $evaluationMetadata = []
    ) {}

    public function toArray(): array
    {
        return [
            'model_identifier' => $this->modelIdentifier,
            'evaluation_period_start' => $this->evaluationPeriodStart,
            'evaluation_period_end' => $this->evaluationPeriodEnd,
            'sample_count' => $this->sampleCount,
            'mae' => $this->mae,
            'rmse' => $this->rmse,
            'wape' => $this->wape,
            'bias' => $this->bias,
            'interval_coverage_90' => $this->intervalCoverage90,
            'evaluation_metadata' => $this->evaluationMetadata,
        ];
    }
}
