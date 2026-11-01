<?php

declare(strict_types=1);

namespace Kaching\Intelligence\DTOs;

final class WeatherItemDTO
{
    public function __construct(
        public readonly float $latitude,
        public readonly float $longitude,
        public readonly string $timestamp,
        public readonly float $temperatureC,
        public readonly float $precipitationMm,
        public readonly string $weatherCondition,
        public readonly int $weatherCode,
        public readonly ?float $windSpeedKmh = null,
        public readonly ?int $relativeHumidityPercent = null,
        public readonly string $forecastHorizon = 'CURRENT',
        public readonly string $providerAttribution = 'Open-Meteo (open-meteo.com)',
        public readonly string $dataFreshness = 'LIVE',
        public readonly array $rawPayload = []
    ) {
    }

    public function toArray(): array
    {
        return [
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'timestamp' => $this->timestamp,
            'temperature_c' => $this->temperatureC,
            'precipitation_mm' => $this->precipitationMm,
            'weather_condition' => $this->weatherCondition,
            'weather_code' => $this->weatherCode,
            'wind_speed_kmh' => $this->windSpeedKmh,
            'relative_humidity_percent' => $this->relativeHumidityPercent,
            'forecast_horizon' => $this->forecastHorizon,
            'provider_attribution' => $this->providerAttribution,
            'data_freshness' => $this->dataFreshness,
            'raw_payload' => $this->rawPayload,
        ];
    }
}
