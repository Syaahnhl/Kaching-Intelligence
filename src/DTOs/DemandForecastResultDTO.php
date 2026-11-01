<?php

namespace Kaching\Intelligence\DTOs;

final class DemandForecastResultDTO
{
    /**
     * @param string $itemIdentifier Product/ingredient identifier
     * @param string $outletIdentifier Authorized outlet identifier
     * @param ForecastHorizonDTO $horizon Requested horizon
     * @param array<ForecastPointDTO> $forecastPoints Daily projected points
     * @param float $totalProjectedDemand Sum of predicted demand over horizon
     * @param string $confidenceLevel 'HIGH', 'MEDIUM', 'LOW'
     * @param string $historyStatus 'SUFFICIENT', 'INSUFFICIENT_HISTORY', 'ZERO_DEMAND'
     * @param ModelMetadataDTO $modelMetadata Engine provenance and model metadata
     * @param array<string> $dataQualityWarnings Warnings regarding missing dates, sparsity, or stale signals
     * @param array<string, mixed> $telemetry Telemetry details
     */
    public function __construct(
        public readonly string $itemIdentifier,
        public readonly string $outletIdentifier,
        public readonly ForecastHorizonDTO $horizon,
        public readonly array $forecastPoints,
        public readonly float $totalProjectedDemand,
        public readonly string $confidenceLevel,
        public readonly string $historyStatus,
        public readonly ModelMetadataDTO $modelMetadata,
        public readonly array $dataQualityWarnings = [],
        public readonly array $telemetry = []
    ) {}

    public function toArray(): array
    {
        return [
            'item_identifier' => $this->itemIdentifier,
            'outlet_identifier' => $this->outletIdentifier,
            'horizon' => $this->horizon->toArray(),
            'forecast_points' => array_map(fn(ForecastPointDTO $p) => $p->toArray(), $this->forecastPoints),
            'total_projected_demand' => $this->totalProjectedDemand,
            'confidence_level' => $this->confidenceLevel,
            'history_status' => $this->historyStatus,
            'model_metadata' => $this->modelMetadata->toArray(),
            'data_quality_warnings' => $this->dataQualityWarnings,
            'telemetry' => $this->telemetry,
        ];
    }
}
