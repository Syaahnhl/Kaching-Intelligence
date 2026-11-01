<?php

namespace Kaching\Intelligence\DTOs;

final class ModelMetadataDTO
{
    /**
     * @param string $modelIdentifier Unique name (e.g. 'catboost_regressor', 'dow_median_baseline')
     * @param string $modelVersion Version string (e.g. 'native-catboost-v1', 'statistical-baseline-v1')
     * @param string $strategyUsed Concrete strategy name
     * @param string $featureVersion Feature schema version (e.g. 'native-37f-v1')
     * @param string $servingOrigin Execution origin (e.g. 'FASTAPI_CATBOOST', 'INTELLIGENCE_LOCAL_ENGINE')
     * @param bool $fallbackApplied Whether this forecast was produced by a fallback mechanism
     * @param array<string, mixed> $provenance Extra feature and pipeline provenance metadata
     */
    public function __construct(
        public readonly string $modelIdentifier,
        public readonly string $modelVersion,
        public readonly string $strategyUsed,
        public readonly string $featureVersion = 'native-37f-v1',
        public readonly string $servingOrigin = 'INTELLIGENCE_LOCAL',
        public readonly bool $fallbackApplied = false,
        public readonly array $provenance = []
    ) {}

    public function toArray(): array
    {
        return [
            'model_identifier' => $this->modelIdentifier,
            'model_version' => $this->modelVersion,
            'strategy_used' => $this->strategyUsed,
            'feature_version' => $this->featureVersion,
            'serving_origin' => $this->servingOrigin,
            'fallback_applied' => $this->fallbackApplied,
            'provenance' => $this->provenance,
        ];
    }
}
