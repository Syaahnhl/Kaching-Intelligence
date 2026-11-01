<?php

namespace Kaching\Intelligence\Tests\Unit;

use Kaching\Intelligence\DTOs\DemandForecastContextDTO;
use Kaching\Intelligence\DTOs\ForecastHorizonDTO;
use Kaching\Intelligence\DemandForecast\CatBoostDemandForecastService;
use PHPUnit\Framework\TestCase;

class CatBoostDemandForecastServiceTest extends TestCase
{
    public function test_it_falls_back_cleanly_when_serving_endpoint_is_not_configured(): void
    {
        $service = new CatBoostDemandForecastService(servingEndpoint: null);

        $series = [
            ['date' => '2026-10-12', 'day_of_week' => 1, 'value' => 10.0],
            ['date' => '2026-10-13', 'day_of_week' => 2, 'value' => 20.0],
            ['date' => '2026-10-14', 'day_of_week' => 3, 'value' => 30.0],
            ['date' => '2026-10-15', 'day_of_week' => 4, 'value' => 40.0],
            ['date' => '2026-10-16', 'day_of_week' => 5, 'value' => 50.0],
            ['date' => '2026-10-17', 'day_of_week' => 6, 'value' => 60.0],
            ['date' => '2026-10-18', 'day_of_week' => 7, 'value' => 70.0],
        ];

        $horizon = new ForecastHorizonDTO('2026-10-19', '2026-10-21', 3);

        $context = new DemandForecastContextDTO(
            itemIdentifier: 'ITEM_CB_TEST',
            itemName: 'CatBoost Test Item',
            category: 'Food',
            uom: 'portion',
            outletIdentifier: 'OUTLET_1',
            cutoffDate: '2026-10-18',
            horizon: $horizon,
            historicalSeries: $series
        );

        $result = $service->forecast($context);

        $this->assertTrue($result->modelMetadata->fallbackApplied);
        $this->assertSame('catboost_regressor', $result->modelMetadata->modelIdentifier);
        $this->assertStringContainsString('fallback to DOW Median baseline applied', $result->dataQualityWarnings[0]);
        $this->assertCount(3, $result->forecastPoints);
    }

    public function test_it_handles_connection_failure_by_falling_back_without_crashing(): void
    {
        // Unreachable port
        $service = new CatBoostDemandForecastService(
            servingEndpoint: 'http://127.0.0.1:54321/predict',
            timeoutMs: 100
        );

        $series = [
            ['date' => '2026-10-12', 'day_of_week' => 1, 'value' => 15.0],
            ['date' => '2026-10-13', 'day_of_week' => 2, 'value' => 25.0],
            ['date' => '2026-10-14', 'day_of_week' => 3, 'value' => 35.0],
            ['date' => '2026-10-15', 'day_of_week' => 4, 'value' => 45.0],
            ['date' => '2026-10-16', 'day_of_week' => 5, 'value' => 55.0],
            ['date' => '2026-10-17', 'day_of_week' => 6, 'value' => 65.0],
            ['date' => '2026-10-18', 'day_of_week' => 7, 'value' => 75.0],
        ];

        $horizon = new ForecastHorizonDTO('2026-10-19', '2026-10-20', 2);

        $context = new DemandForecastContextDTO(
            itemIdentifier: 'ITEM_CB_UNREACHABLE',
            itemName: 'Unreachable Item',
            category: 'Food',
            uom: 'portion',
            outletIdentifier: 'OUTLET_1',
            cutoffDate: '2026-10-18',
            horizon: $horizon,
            historicalSeries: $series
        );

        $result = $service->forecast($context);

        $this->assertTrue($result->modelMetadata->fallbackApplied);
        $this->assertStringContainsString('CatBoost serving call failed', $result->dataQualityWarnings[0]);
    }
}
