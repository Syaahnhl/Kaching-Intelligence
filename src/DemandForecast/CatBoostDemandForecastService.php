<?php

namespace Kaching\Intelligence\DemandForecast;

use DateTimeImmutable;
use Kaching\Intelligence\Contracts\DemandForecastingEngineInterface;
use Kaching\Intelligence\DTOs\DemandForecastContextDTO;
use Kaching\Intelligence\DTOs\DemandForecastResultDTO;
use Kaching\Intelligence\DTOs\ForecastPointDTO;
use Kaching\Intelligence\DTOs\ModelMetadataDTO;
use RuntimeException;

class CatBoostDemandForecastService implements DemandForecastingEngineInterface
{
    public const MODEL_IDENTIFIER = 'catboost_regressor';
    public const MODEL_VERSION = 'native-catboost-v1';
    public const STRATEGY = 'CATBOOST_MACHINE_LEARNING';
    public const SERVING_ORIGIN = 'CATBOOST_SERVING_CLIENT';

    protected ?string $servingEndpoint;
    protected int $timeoutMs;
    protected DowMedianDemandForecastService $fallbackEngine;

    public function __construct(
        ?string $servingEndpoint = null,
        int $timeoutMs = 1500,
        ?DowMedianDemandForecastService $fallbackEngine = null
    ) {
        $this->servingEndpoint = $servingEndpoint;
        $this->timeoutMs = $timeoutMs;
        $this->fallbackEngine = $fallbackEngine ?? new DowMedianDemandForecastService();
    }

    public function getEngineIdentifier(): string
    {
        return self::MODEL_IDENTIFIER;
    }

    public function forecast(DemandForecastContextDTO $context): DemandForecastResultDTO
    {
        // 1. Check if serving endpoint is configured and reachable
        if (empty($this->servingEndpoint)) {
            // Documented: Fallback to DOW Median deterministically
            return $this->produceFallbackResult(
                $context,
                'CatBoost serving endpoint not configured; fallback to DOW Median baseline applied.'
            );
        }

        // 2. Prepare payload matching CatBoost native feature schema (37 features)
        $payload = $this->buildServingPayload($context);

        try {
            $response = $this->executeHttpRequest($this->servingEndpoint, $payload);
            return $this->parseServingResponse($context, $response);
        } catch (\Throwable $e) {
            return $this->produceFallbackResult(
                $context,
                "CatBoost serving call failed ({$e->getMessage()}); fallback to DOW Median baseline applied."
            );
        }
    }

    protected function buildServingPayload(DemandForecastContextDTO $context): array
    {
        return [
            'item_id' => $context->itemIdentifier,
            'outlet_id' => $context->outletIdentifier,
            'cutoff_date' => $context->cutoffDate,
            'horizon_days' => $context->horizon->horizonDays,
            'historical_series' => $context->historicalSeries,
            'calendar_features' => $context->calendarFeatures,
            'weather_features' => $context->weatherFeatures,
        ];
    }

    protected function executeHttpRequest(string $endpoint, array $payload): array
    {
        $ch = curl_init($endpoint);
        if ($ch === false) {
            throw new RuntimeException('Failed to initialize cURL.');
        }

        $jsonPayload = json_encode($payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, $this->timeoutMs);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
        ]);

        $body = curl_exec($ch);
        $err = curl_error($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err) {
            throw new RuntimeException("cURL error: {$err}");
        }

        if ($statusCode >= 400 || !$body) {
            throw new RuntimeException("HTTP {$statusCode} from CatBoost serving: {$body}");
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Invalid JSON received from CatBoost serving.');
        }

        return $decoded;
    }

    protected function parseServingResponse(DemandForecastContextDTO $context, array $response): DemandForecastResultDTO
    {
        $rawPoints = $response['forecast_points'] ?? $response['points'] ?? [];
        $forecastPoints = [];
        $totalDemand = 0.0;

        foreach ($rawPoints as $pt) {
            $date = (string) ($pt['date'] ?? '');
            $dow = (int) ($pt['day_of_week'] ?? (new DateTimeImmutable($date))->format('N'));
            $predQty = max(0.0, (float) ($pt['predicted_quantity'] ?? $pt['predicted_demand'] ?? 0.0));
            $p10 = isset($pt['lower_bound_p10']) ? (float) $pt['lower_bound_p10'] : null;
            $p90 = isset($pt['upper_bound_p90']) ? (float) $pt['upper_bound_p90'] : null;

            $forecastPoints[] = new ForecastPointDTO(
                date: $date,
                dayOfWeek: $dow,
                predictedQuantity: round($predQty, 3),
                lowerBoundP10: $p10 !== null ? round($p10, 3) : null,
                upperBoundP90: $p90 !== null ? round($p90, 3) : null,
                rawModelOutput: $predQty,
                isClipped: false,
                pointMetadata: [
                    'source' => 'CATBOOST_SERVING_CLIENT',
                ]
            );

            $totalDemand += $predQty;
        }

        $meta = new ModelMetadataDTO(
            modelIdentifier: self::MODEL_IDENTIFIER,
            modelVersion: (string) ($response['model_version'] ?? self::MODEL_VERSION),
            strategyUsed: self::STRATEGY,
            featureVersion: 'native-37f-v1',
            servingOrigin: self::SERVING_ORIGIN,
            fallbackApplied: false,
            provenance: [
                'serving_endpoint' => $this->servingEndpoint,
                'latency_ms' => $response['latency_ms'] ?? null,
            ]
        );

        return new DemandForecastResultDTO(
            itemIdentifier: $context->itemIdentifier,
            outletIdentifier: $context->outletIdentifier,
            horizon: $context->horizon,
            forecastPoints: $forecastPoints,
            totalProjectedDemand: round($totalDemand, 3),
            confidenceLevel: 'HIGH',
            historyStatus: 'SUFFICIENT',
            modelMetadata: $meta,
            dataQualityWarnings: [],
            telemetry: [
                'serving_origin' => self::SERVING_ORIGIN,
            ]
        );
    }

    protected function produceFallbackResult(DemandForecastContextDTO $context, string $reason): DemandForecastResultDTO
    {
        $baseline = $this->fallbackEngine->forecast($context);
        $warnings = array_merge([$reason], $baseline->dataQualityWarnings);

        $fallbackMeta = new ModelMetadataDTO(
            modelIdentifier: self::MODEL_IDENTIFIER,
            modelVersion: self::MODEL_VERSION,
            strategyUsed: 'FALLBACK_' . $baseline->modelMetadata->strategyUsed,
            featureVersion: 'native-statistical-dow',
            servingOrigin: 'INTELLIGENCE_FALLBACK_ENGINE',
            fallbackApplied: true,
            provenance: [
                'fallback_reason' => $reason,
                'baseline_model' => $baseline->modelMetadata->modelIdentifier,
            ]
        );

        return new DemandForecastResultDTO(
            itemIdentifier: $context->itemIdentifier,
            outletIdentifier: $context->outletIdentifier,
            horizon: $context->horizon,
            forecastPoints: $baseline->forecastPoints,
            totalProjectedDemand: $baseline->totalProjectedDemand,
            confidenceLevel: 'MEDIUM',
            historyStatus: $baseline->historyStatus,
            modelMetadata: $fallbackMeta,
            dataQualityWarnings: $warnings,
            telemetry: [
                'catboost_serving_active' => false,
                'fallback_applied' => true,
            ]
        );
    }
}
