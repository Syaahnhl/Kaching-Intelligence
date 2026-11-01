<?php

namespace Kaching\Intelligence\Tests\Unit;

use Kaching\Intelligence\DTOs\DemandForecastContextDTO;
use Kaching\Intelligence\DTOs\ForecastHorizonDTO;
use Kaching\Intelligence\DemandForecast\WeatherEnrichedDemandForecastService;
use PHPUnit\Framework\TestCase;

class WeatherEnrichedDemandForecastServiceTest extends TestCase
{
    private WeatherEnrichedDemandForecastService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new WeatherEnrichedDemandForecastService();
    }

    public function test_it_degrades_gracefully_when_weather_features_are_absent(): void
    {
        $series = [
            ['date' => '2026-10-12', 'day_of_week' => 1, 'value' => 20.0],
            ['date' => '2026-10-13', 'day_of_week' => 2, 'value' => 25.0],
            ['date' => '2026-10-14', 'day_of_week' => 3, 'value' => 22.0],
            ['date' => '2026-10-15', 'day_of_week' => 4, 'value' => 24.0],
            ['date' => '2026-10-16', 'day_of_week' => 5, 'value' => 30.0],
            ['date' => '2026-10-17', 'day_of_week' => 6, 'value' => 40.0],
            ['date' => '2026-10-18', 'day_of_week' => 7, 'value' => 35.0],
        ];

        $horizon = new ForecastHorizonDTO('2026-10-19', '2026-10-21', 3);

        $context = new DemandForecastContextDTO(
            itemIdentifier: 'ITEM_HOT_COFFEE',
            itemName: 'Hot Coffee',
            category: 'Beverage',
            uom: 'cup',
            outletIdentifier: 'OUTLET_1',
            cutoffDate: '2026-10-18',
            horizon: $horizon,
            historicalSeries: $series,
            weatherFeatures: [] // EMPTY
        );

        $result = $this->service->forecast($context);

        $this->assertTrue($result->modelMetadata->fallbackApplied);
        $this->assertStringContainsString('Weather data absent', $result->dataQualityWarnings[0]);
        $this->assertCount(3, $result->forecastPoints);
    }

    public function test_it_adjusts_beverage_forecast_on_rainy_day(): void
    {
        $series = [
            ['date' => '2026-10-12', 'day_of_week' => 1, 'value' => 100.0],
            ['date' => '2026-10-13', 'day_of_week' => 2, 'value' => 100.0],
            ['date' => '2026-10-14', 'day_of_week' => 3, 'value' => 100.0],
            ['date' => '2026-10-15', 'day_of_week' => 4, 'value' => 100.0],
            ['date' => '2026-10-16', 'day_of_week' => 5, 'value' => 100.0],
            ['date' => '2026-10-17', 'day_of_week' => 6, 'value' => 100.0],
            ['date' => '2026-10-18', 'day_of_week' => 7, 'value' => 100.0],
        ];

        $horizon = new ForecastHorizonDTO('2026-10-19', '2026-10-19', 1);

        // Rainy weather on 2026-10-19
        $weather = [
            'source' => 'OPEN_METEO_BMKG',
            'daily' => [
                '2026-10-19' => [
                    'is_rainy' => true,
                    'precipitation_mm' => 15.0,
                    'temp_mean_c' => 24.0,
                    'rain_prob_pct' => 85.0,
                ],
            ],
        ];

        $context = new DemandForecastContextDTO(
            itemIdentifier: 'ITEM_ICE_TEA',
            itemName: 'Es Teh Manis',
            category: 'Minuman Dingin',
            uom: 'cup',
            outletIdentifier: 'OUTLET_1',
            cutoffDate: '2026-10-18',
            horizon: $horizon,
            historicalSeries: $series,
            weatherFeatures: $weather
        );

        $result = $this->service->forecast($context);

        $this->assertFalse($result->modelMetadata->fallbackApplied);
        // Cold drink demand should be dampened by -12% -> 88.0
        $this->assertEquals(88.0, $result->forecastPoints[0]->predictedQuantity);
        $this->assertEquals(0.88, $result->forecastPoints[0]->pointMetadata['weather_multiplier']);
    }
}
