<?php

declare(strict_types=1);

namespace Kaching\Intelligence\Contracts;

use Kaching\Intelligence\DTOs\WeatherResultDTO;

interface WeatherProviderInterface
{
    public function getIdentifier(): string;

    public function isConfigured(): bool;

    /**
     * @param float $latitude Authorized latitude
     * @param float $longitude Authorized longitude
     * @param array{forecast_days?: int, hourly?: bool} $options
     */
    public function fetchWeather(float $latitude, float $longitude, array $options = []): WeatherResultDTO;
}
