<?php

namespace Kaching\Intelligence\Tests\Unit;

use Kaching\Intelligence\DTOs\DemandForecastContextDTO;
use Kaching\Intelligence\DTOs\ForecastHorizonDTO;
use Kaching\Intelligence\DemandForecast\DowMedianDemandForecastService;
use PHPUnit\Framework\TestCase;

class DowMedianDemandForecastServiceTest extends TestCase
{
    private DowMedianDemandForecastService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DowMedianDemandForecastService(maxWeeks: 8, minimumRequiredDays: 7);
    }

    public function test_it_computes_exact_median_per_day_of_week(): void
    {
        // Construct 14 days of history (2 Mondays, 2 Tuesdays, etc.)
        // Mondays: 2026-10-05 (10.0), 2026-10-12 (20.0) -> median 15.0
        // Tuesdays: 2026-10-06 (15.0), 2026-10-13 (25.0) -> median 20.0
        $series = [
            ['date' => '2026-10-05', 'day_of_week' => 1, 'value' => 10.0],
            ['date' => '2026-10-06', 'day_of_week' => 2, 'value' => 15.0],
            ['date' => '2026-10-07', 'day_of_week' => 3, 'value' => 12.0],
            ['date' => '2026-10-08', 'day_of_week' => 4, 'value' => 14.0],
            ['date' => '2026-10-09', 'day_of_week' => 5, 'value' => 30.0],
            ['date' => '2026-10-10', 'day_of_week' => 6, 'value' => 40.0],
            ['date' => '2026-10-11', 'day_of_week' => 7, 'value' => 35.0],
            ['date' => '2026-10-12', 'day_of_week' => 1, 'value' => 20.0],
            ['date' => '2026-10-13', 'day_of_week' => 2, 'value' => 25.0],
            ['date' => '2026-10-14', 'day_of_week' => 3, 'value' => 16.0],
            ['date' => '2026-10-15', 'day_of_week' => 4, 'value' => 18.0],
            ['date' => '2026-10-16', 'day_of_week' => 5, 'value' => 34.0],
            ['date' => '2026-10-17', 'day_of_week' => 6, 'value' => 42.0],
            ['date' => '2026-10-18', 'day_of_week' => 7, 'value' => 38.0],
        ];

        // Horizon: Monday 2026-10-19 to Sunday 2026-10-25 (7 days)
        $horizon = new ForecastHorizonDTO('2026-10-19', '2026-10-25', 7);

        $context = new DemandForecastContextDTO(
            itemIdentifier: 'ITEM_LATTE_1',
            itemName: 'Caffe Latte',
            category: 'Beverage',
            uom: 'cup',
            outletIdentifier: 'OUTLET_1',
            cutoffDate: '2026-10-18',
            horizon: $horizon,
            historicalSeries: $series
        );

        $result = $this->service->forecast($context);

        $this->assertSame('ITEM_LATTE_1', $result->itemIdentifier);
        $this->assertSame('OUTLET_1', $result->outletIdentifier);
        $this->assertCount(7, $result->forecastPoints);
        $this->assertSame('SUFFICIENT', $result->historyStatus);
        $this->assertSame('HIGH', $result->confidenceLevel);

        // Day 1: Monday -> median of [10.0, 20.0] = 15.0
        $this->assertEquals(15.0, $result->forecastPoints[0]->predictedQuantity);
        $this->assertSame('2026-10-19', $result->forecastPoints[0]->date);
        $this->assertSame(1, $result->forecastPoints[0]->dayOfWeek);

        // Day 2: Tuesday -> median of [15.0, 25.0] = 20.0
        $this->assertEquals(20.0, $result->forecastPoints[1]->predictedQuantity);

        // Total projected demand
        $this->assertGreaterThan(0.0, $result->totalProjectedDemand);
        $this->assertFalse($result->modelMetadata->fallbackApplied);
    }

    public function test_it_handles_sparse_and_insufficient_history(): void
    {
        $series = [
            ['date' => '2026-10-16', 'day_of_week' => 5, 'value' => 10.0],
            ['date' => '2026-10-17', 'day_of_week' => 6, 'value' => 15.0],
        ];

        $horizon = new ForecastHorizonDTO('2026-10-18', '2026-10-24', 7);

        $context = new DemandForecastContextDTO(
            itemIdentifier: 'ITEM_SPARSE',
            itemName: 'Sparse Item',
            category: 'Retail',
            uom: 'pcs',
            outletIdentifier: 'OUTLET_1',
            cutoffDate: '2026-10-17',
            horizon: $horizon,
            historicalSeries: $series
        );

        $result = $this->service->forecast($context);

        $this->assertSame('INSUFFICIENT_HISTORY', $result->historyStatus);
        $this->assertSame('LOW', $result->confidenceLevel);
        $this->assertNotEmpty($result->dataQualityWarnings);
    }

    public function test_it_handles_zero_demand_series(): void
    {
        $series = [];
        for ($i = 1; $i <= 10; $i++) {
            $series[] = ['date' => "2026-10-0{$i}", 'day_of_week' => (($i - 1) % 7) + 1, 'value' => 0.0];
        }

        $horizon = new ForecastHorizonDTO('2026-10-11', '2026-10-17', 7);

        $context = new DemandForecastContextDTO(
            itemIdentifier: 'ITEM_ZERO',
            itemName: 'Zero Demand Item',
            category: 'Retail',
            uom: 'pcs',
            outletIdentifier: 'OUTLET_1',
            cutoffDate: '2026-10-10',
            horizon: $horizon,
            historicalSeries: $series
        );

        $result = $this->service->forecast($context);

        $this->assertSame('ZERO_DEMAND', $result->historyStatus);
        $this->assertEquals(0.0, $result->totalProjectedDemand);
    }

    public function test_it_is_robust_against_extreme_outliers(): void
    {
        // 3 Mondays: 10, 12, 500 (catering promo spike)
        // Median must remain 12.0, NOT shifted by 500
        $series = [
            ['date' => '2026-09-28', 'day_of_week' => 1, 'value' => 10.0],
            ['date' => '2026-10-05', 'day_of_week' => 1, 'value' => 12.0],
            ['date' => '2026-10-12', 'day_of_week' => 1, 'value' => 500.0],
        ];

        $median = $this->service->calculateDowMedianForDate($series, 1);
        $this->assertEquals(12.0, $median);
    }
}
