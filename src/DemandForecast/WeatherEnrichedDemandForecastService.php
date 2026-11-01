<?php

namespace Kaching\Intelligence\DemandForecast;

use DateTimeImmutable;
use Kaching\Intelligence\Contracts\DemandForecastingEngineInterface;
use Kaching\Intelligence\DTOs\DemandForecastContextDTO;
use Kaching\Intelligence\DTOs\DemandForecastResultDTO;
use Kaching\Intelligence\DTOs\ForecastPointDTO;
use Kaching\Intelligence\DTOs\ModelMetadataDTO;

class WeatherEnrichedDemandForecastService implements DemandForecastingEngineInterface
{
    public const MODEL_IDENTIFIER = 'weather_enriched_forecast';
    public const MODEL_VERSION = 'weather-enriched-v1';
    public const STRATEGY = 'WEATHER_ENRICHED_DOW';
    public const SERVING_ORIGIN = 'INTELLIGENCE_LOCAL_ENGINE';

    protected DowMedianDemandForecastService $baselineEngine;

    public function __construct(?DowMedianDemandForecastService $baselineEngine = null)
    {
        $this->baselineEngine = $baselineEngine ?? new DowMedianDemandForecastService();
    }

    public function getEngineIdentifier(): string
    {
        return self::MODEL_IDENTIFIER;
    }

    public function forecast(DemandForecastContextDTO $context): DemandForecastResultDTO
    {
        // 1. Compute Base DOW Median Forecast
        $baselineResult = $this->baselineEngine->forecast($context);

        $weatherFeatures = $context->weatherFeatures;
        $warnings = $baselineResult->dataQualityWarnings;

        // Check weather availability
        $hasValidWeather = !empty($weatherFeatures) && !empty($weatherFeatures['daily']);
        if (!$hasValidWeather) {
            $warnings[] = 'Weather data absent or empty; gracefully degraded to pure DOW Median baseline.';
            
            // Return baseline with fallback model metadata
            $fallbackMeta = new ModelMetadataDTO(
                modelIdentifier: self::MODEL_IDENTIFIER,
                modelVersion: self::MODEL_VERSION,
                strategyUsed: 'DEGRADED_DOW_MEDIAN_NO_WEATHER',
                featureVersion: 'weather-enrichment-v1',
                servingOrigin: self::SERVING_ORIGIN,
                fallbackApplied: true,
                provenance: array_merge($baselineResult->modelMetadata->provenance, [
                    'weather_enrichment' => 'FALLBACK_ABSENT',
                ])
            );

            return new DemandForecastResultDTO(
                itemIdentifier: $baselineResult->itemIdentifier,
                outletIdentifier: $baselineResult->outletIdentifier,
                horizon: $baselineResult->horizon,
                forecastPoints: $baselineResult->forecastPoints,
                totalProjectedDemand: $baselineResult->totalProjectedDemand,
                confidenceLevel: $baselineResult->confidenceLevel,
                historyStatus: $baselineResult->historyStatus,
                modelMetadata: $fallbackMeta,
                dataQualityWarnings: $warnings,
                telemetry: array_merge($baselineResult->telemetry, [
                    'weather_applied' => false,
                ])
            );
        }

        // 2. Weather Feature Mapping & Point Modification
        $dailyWeather = $weatherFeatures['daily'] ?? [];
        $weatherSource = $weatherFeatures['source'] ?? 'OPEN_METEO';
        $enrichedPoints = [];
        $totalProjected = 0.0;
        $category = strtolower($context->category);

        foreach ($baselineResult->forecastPoints as $point) {
            $date = $point->date;
            $dayWeather = $dailyWeather[$date] ?? null;

            $multiplier = 1.0;
            $weatherMeta = [
                'weather_source' => $weatherSource,
                'weather_available' => false,
            ];

            if ($dayWeather !== null) {
                $isRainy = !empty($dayWeather['is_rainy']) || ((float) ($dayWeather['precipitation_mm'] ?? 0.0) >= 5.0);
                $tempMean = (float) ($dayWeather['temp_mean_c'] ?? 28.0);
                $rainProb = (float) ($dayWeather['rain_prob_pct'] ?? 0.0);

                $weatherMeta = [
                    'weather_source' => $weatherSource,
                    'weather_available' => true,
                    'is_rainy' => $isRainy,
                    'temp_mean_c' => $tempMean,
                    'rain_prob_pct' => $rainProb,
                ];

                // Category-specific empirical weather sensitivity elasticity:
                // Beverage / Cold drinks decrease during heavy rain (-10% to -15%), increase in high heat (>32C: +10%)
                // Hot soup / Comfort food / Delivery increase slightly in rain (+5% to +10%)
                if ($isRainy) {
                    if (str_contains($category, 'minuman') || str_contains($category, 'beverage') || str_contains($category, 'drink') || str_contains($category, 'es')) {
                        $multiplier = 0.88; // -12% on cold drinks during rain
                    } elseif (str_contains($category, 'kuah') || str_contains($category, 'soup') || str_contains($category, 'mie')) {
                        $multiplier = 1.08; // +8% comfort foods
                    } else {
                        $multiplier = 0.95; // general foot traffic slight dampening
                    }
                } elseif ($tempMean >= 33.0) {
                    if (str_contains($category, 'minuman') || str_contains($category, 'beverage') || str_contains($category, 'drink') || str_contains($category, 'es')) {
                        $multiplier = 1.12; // +12% thirst demand
                    }
                }
            }

            $adjustedQty = max(0.0, round($point->predictedQuantity * $multiplier, 3));
            $adjustedP10 = max(0.0, round(($point->lowerBoundP10 ?? 0.0) * $multiplier, 3));
            $adjustedP90 = max(0.0, round(($point->upperBoundP90 ?? 0.0) * $multiplier, 3));

            $enrichedPoints[] = new ForecastPointDTO(
                date: $point->date,
                dayOfWeek: $point->dayOfWeek,
                predictedQuantity: $adjustedQty,
                lowerBoundP10: $adjustedP10,
                upperBoundP90: $adjustedP90,
                rawModelOutput: $adjustedQty,
                isClipped: false,
                pointMetadata: array_merge($point->pointMetadata, [
                    'weather_multiplier' => $multiplier,
                    'weather_data' => $weatherMeta,
                ])
            );

            $totalProjected += $adjustedQty;
        }

        $meta = new ModelMetadataDTO(
            modelIdentifier: self::MODEL_IDENTIFIER,
            modelVersion: self::MODEL_VERSION,
            strategyUsed: self::STRATEGY,
            featureVersion: 'weather-enrichment-v1',
            servingOrigin: self::SERVING_ORIGIN,
            fallbackApplied: false,
            provenance: [
                'weather_source' => $weatherSource,
                'baseline_model' => $baselineResult->modelMetadata->modelIdentifier,
            ]
        );

        return new DemandForecastResultDTO(
            itemIdentifier: $context->itemIdentifier,
            outletIdentifier: $context->outletIdentifier,
            horizon: $context->horizon,
            forecastPoints: $enrichedPoints,
            totalProjectedDemand: round($totalProjected, 3),
            confidenceLevel: $baselineResult->confidenceLevel,
            historyStatus: $baselineResult->historyStatus,
            modelMetadata: $meta,
            dataQualityWarnings: $warnings,
            telemetry: [
                'weather_applied' => true,
                'weather_source' => $weatherSource,
            ]
        );
    }
}
