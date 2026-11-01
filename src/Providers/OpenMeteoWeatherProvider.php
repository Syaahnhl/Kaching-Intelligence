<?php

declare(strict_types=1);

namespace Kaching\Intelligence\Providers;

use Kaching\Intelligence\Contracts\WeatherProviderInterface;
use Kaching\Intelligence\DTOs\WeatherItemDTO;
use Kaching\Intelligence\DTOs\WeatherResultDTO;

class OpenMeteoWeatherProvider implements WeatherProviderInterface
{
    /**
     * @var callable|null Test / mock HTTP runner: fn(string $url, array $headers, array $params, int $timeout): array{status: int, body: string, headers: array}
     */
    protected static $mockTransport = null;

    public const WMO_CODE_MAP = [
        0 => 'Cerah / Clear Sky',
        1 => 'Sebagian Besar Cerah / Mainly Clear',
        2 => 'Berawan Sebagian / Partly Cloudy',
        3 => 'Mendung / Overcast',
        45 => 'Berkabut / Fog',
        48 => 'Kabut Rime / Depositing Rime Fog',
        51 => 'Gerimis Ringan / Light Drizzle',
        53 => 'Gerimis Sedang / Moderate Drizzle',
        55 => 'Gerimis Lebat / Dense Drizzle',
        61 => 'Hujan Ringan / Slight Rain',
        63 => 'Hujan Sedang / Moderate Rain',
        65 => 'Hujan Lebat / Heavy Rain',
        71 => 'Salju Ringan / Slight Snow',
        73 => 'Salju Sedang / Moderate Snow',
        75 => 'Salju Lebat / Heavy Snow',
        80 => 'Hujan Rintik / Slight Rain Showers',
        81 => 'Hujan Deras / Moderate Rain Showers',
        82 => 'Hujan Badai / Violent Rain Showers',
        95 => 'Badai Petir / Thunderstorm',
        96 => 'Badai Petir & Es / Thunderstorm with Hail',
        99 => 'Badai Petir Dahsyat / Heavy Thunderstorm',
    ];

    public function __construct(
        protected readonly string $endpointUrl = 'https://api.open-meteo.com/v1/forecast',
        protected readonly int $timeoutSeconds = 5
    ) {
    }

    public static function setMockTransport(?callable $transport): void
    {
        self::$mockTransport = $transport;
    }

    public function getIdentifier(): string
    {
        return 'OPEN_METEO';
    }

    public function isConfigured(): bool
    {
        return !empty(trim($this->endpointUrl));
    }

    public function fetchWeather(float $latitude, float $longitude, array $options = []): WeatherResultDTO
    {
        $timestamp = gmdate('Y-m-d\TH:i:s\Z');
        $attribution = 'Open-Meteo Weather API (open-meteo.com)';

        if (!$this->isConfigured()) {
            return new WeatherResultDTO(
                success: false,
                status: WeatherResultDTO::STATUS_UNCONFIGURED,
                providerIdentifier: $this->getIdentifier(),
                sourceAttribution: $attribution,
                current: null,
                forecastItems: [],
                errorMessage: 'Open-Meteo endpoint is not configured.',
                httpStatus: null,
                retrievalTimestamp: $timestamp,
                isStale: false
            );
        }

        $forecastDays = (int) ($options['forecast_days'] ?? 3);
        $forecastDays = max(1, min(7, $forecastDays));

        $queryParams = [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'current' => 'temperature_2m,relative_humidity_2m,precipitation,weather_code,wind_speed_10m',
            'hourly' => 'temperature_2m,precipitation,weather_code',
            'forecast_days' => $forecastDays,
            'timezone' => 'auto',
        ];

        $headers = [
            'Accept' => 'application/json',
        ];

        $url = $this->endpointUrl . '?' . http_build_query($queryParams);

        try {
            $response = $this->executeHttp($url, $headers, $this->timeoutSeconds);
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            $isTimeout = str_contains(strtolower($msg), 'timeout') || str_contains(strtolower($msg), 'timed out');

            return new WeatherResultDTO(
                success: false,
                status: $isTimeout ? WeatherResultDTO::STATUS_TIMEOUT : WeatherResultDTO::STATUS_UNAVAILABLE,
                providerIdentifier: $this->getIdentifier(),
                sourceAttribution: $attribution,
                current: null,
                forecastItems: [],
                errorMessage: $isTimeout ? 'Request timed out contacting Open-Meteo API.' : 'Failed to connect to Open-Meteo: ' . $msg,
                httpStatus: null,
                retrievalTimestamp: $timestamp,
                isStale: false,
                metadata: ['exception' => get_class($e)]
            );
        }

        $statusCode = $response['status'];
        if ($statusCode === 429) {
            return new WeatherResultDTO(
                success: false,
                status: WeatherResultDTO::STATUS_RATE_LIMIT,
                providerIdentifier: $this->getIdentifier(),
                sourceAttribution: $attribution,
                current: null,
                forecastItems: [],
                errorMessage: 'Open-Meteo API rate limit exceeded.',
                httpStatus: $statusCode,
                retrievalTimestamp: $timestamp,
                isStale: false
            );
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            return new WeatherResultDTO(
                success: false,
                status: WeatherResultDTO::STATUS_UNAVAILABLE,
                providerIdentifier: $this->getIdentifier(),
                sourceAttribution: $attribution,
                current: null,
                forecastItems: [],
                errorMessage: 'Open-Meteo API returned HTTP ' . $statusCode,
                httpStatus: $statusCode,
                retrievalTimestamp: $timestamp,
                isStale: false
            );
        }

        $decoded = json_decode($response['body'], true);
        if (!is_array($decoded)) {
            return new WeatherResultDTO(
                success: false,
                status: WeatherResultDTO::STATUS_INVALID_RESPONSE,
                providerIdentifier: $this->getIdentifier(),
                sourceAttribution: $attribution,
                current: null,
                forecastItems: [],
                errorMessage: 'Malformed JSON payload received from Open-Meteo API.',
                httpStatus: $statusCode,
                retrievalTimestamp: $timestamp,
                isStale: false
            );
        }

        $currentData = $decoded['current'] ?? null;
        $hourlyData = $decoded['hourly'] ?? null;

        if (empty($currentData) && empty($hourlyData)) {
            return new WeatherResultDTO(
                success: true,
                status: WeatherResultDTO::STATUS_EMPTY,
                providerIdentifier: $this->getIdentifier(),
                sourceAttribution: $attribution,
                current: null,
                forecastItems: [],
                errorMessage: null,
                httpStatus: $statusCode,
                retrievalTimestamp: $timestamp,
                isStale: false
            );
        }

        $currentDTO = null;
        if (is_array($currentData) && isset($currentData['time'])) {
            $code = (int) ($currentData['weather_code'] ?? 0);
            $cond = self::WMO_CODE_MAP[$code] ?? 'Cuaca Normal';

            $currentDTO = new WeatherItemDTO(
                latitude: (float) ($decoded['latitude'] ?? $latitude),
                longitude: (float) ($decoded['longitude'] ?? $longitude),
                timestamp: (string) $currentData['time'],
                temperatureC: (float) ($currentData['temperature_2m'] ?? 0.0),
                precipitationMm: (float) ($currentData['precipitation'] ?? 0.0),
                weatherCondition: $cond,
                weatherCode: $code,
                windSpeedKmh: isset($currentData['wind_speed_10m']) ? (float) $currentData['wind_speed_10m'] : null,
                relativeHumidityPercent: isset($currentData['relative_humidity_2m']) ? (int) $currentData['relative_humidity_2m'] : null,
                forecastHorizon: 'CURRENT',
                providerAttribution: $attribution,
                dataFreshness: 'LIVE',
                rawPayload: $currentData
            );
        }

        $forecastItems = [];
        if (is_array($hourlyData) && isset($hourlyData['time']) && is_array($hourlyData['time'])) {
            $times = $hourlyData['time'];
            $temps = $hourlyData['temperature_2m'] ?? [];
            $precips = $hourlyData['precipitation'] ?? [];
            $codes = $hourlyData['weather_code'] ?? [];

            $limit = min(24, count($times)); // Next 24 hours
            for ($i = 0; $i < $limit; $i++) {
                $code = (int) ($codes[$i] ?? 0);
                $cond = self::WMO_CODE_MAP[$code] ?? 'Cuaca Normal';

                $forecastItems[] = new WeatherItemDTO(
                    latitude: (float) ($decoded['latitude'] ?? $latitude),
                    longitude: (float) ($decoded['longitude'] ?? $longitude),
                    timestamp: (string) $times[$i],
                    temperatureC: (float) ($temps[$i] ?? 0.0),
                    precipitationMm: (float) ($precips[$i] ?? 0.0),
                    weatherCondition: $cond,
                    weatherCode: $code,
                    windSpeedKmh: null,
                    relativeHumidityPercent: null,
                    forecastHorizon: 'HOURLY_' . ($i + 1),
                    providerAttribution: $attribution,
                    dataFreshness: 'FORECAST',
                    rawPayload: [
                        'time' => $times[$i],
                        'temperature' => $temps[$i] ?? null,
                        'precipitation' => $precips[$i] ?? null,
                        'weather_code' => $code,
                    ]
                );
            }
        }

        return new WeatherResultDTO(
            success: true,
            status: WeatherResultDTO::STATUS_SUCCESS,
            providerIdentifier: $this->getIdentifier(),
            sourceAttribution: $attribution,
            current: $currentDTO,
            forecastItems: $forecastItems,
            errorMessage: null,
            httpStatus: $statusCode,
            retrievalTimestamp: $timestamp,
            isStale: false,
            metadata: [
                'elevation' => $decoded['elevation'] ?? null,
                'timezone' => $decoded['timezone'] ?? null,
            ]
        );
    }

    /**
     * @return array{status: int, body: string, headers: array}
     */
    protected function executeHttp(string $url, array $headers, int $timeout): array
    {
        if (self::$mockTransport !== null) {
            return (self::$mockTransport)($url, $headers, [], $timeout);
        }

        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('Failed to initialize cURL handle');
        }

        $flatHeaders = [];
        foreach ($headers as $k => $v) {
            $flatHeaders[] = "$k: $v";
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $flatHeaders,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $body = curl_exec($ch);
        $err = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err !== '') {
            throw new \RuntimeException($err);
        }

        return [
            'status' => $status,
            'body'   => (string) $body,
            'headers' => [],
        ];
    }
}
