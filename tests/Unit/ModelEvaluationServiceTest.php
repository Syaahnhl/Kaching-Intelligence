<?php

namespace Kaching\Intelligence\Tests\Unit;

use Kaching\Intelligence\DemandForecast\DowMedianDemandForecastService;
use Kaching\Intelligence\DemandForecast\ModelEvaluationService;
use PHPUnit\Framework\TestCase;

class ModelEvaluationServiceTest extends TestCase
{
    private ModelEvaluationService $evaluator;
    private DowMedianDemandForecastService $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluator = new ModelEvaluationService();
        $this->engine = new DowMedianDemandForecastService();
    }

    public function test_it_performs_temporal_backtesting_and_computes_metrics(): void
    {
        // 21 days of synthetic history
        // Split date: 2026-10-14 (14 days train, 7 days test: 2026-10-15 to 2026-10-21)
        $series = [];
        $startDate = new \DateTimeImmutable('2026-10-01');

        // Pattern: Mon-Fri ~20 units, Sat-Sun ~40 units
        for ($i = 0; $i < 21; $i++) {
            $cur = $startDate->modify("+{$i} day");
            $dow = (int) $cur->format('N');
            $base = ($dow >= 6) ? 40.0 : 20.0;
            // Introduce mild variance (+- 2)
            $val = $base + (($i % 3) - 1);

            $series[] = [
                'date' => $cur->format('Y-m-d'),
                'day_of_week' => $dow,
                'value' => (float) $val,
            ];
        }

        $eval = $this->evaluator->evaluateTemporal(
            engine: $this->engine,
            fullSeries: $series,
            splitDate: '2026-10-14',
            horizonDays: 7
        );

        $this->assertSame('dow_median_baseline', $eval->modelIdentifier);
        $this->assertSame(7, $eval->sampleCount);
        $this->assertGreaterThanOrEqual(0.0, $eval->mae);
        $this->assertGreaterThanOrEqual(0.0, $eval->rmse);
        $this->assertNotNull($eval->wape);
        $this->assertGreaterThanOrEqual(0.0, $eval->wape);
        $this->assertNotNull($eval->intervalCoverage90);
        $this->assertGreaterThan(0.5, $eval->intervalCoverage90);
    }

    public function test_it_handles_zero_actual_demand_without_division_by_zero(): void
    {
        $series = [
            ['date' => '2026-10-01', 'day_of_week' => 4, 'value' => 0.0],
            ['date' => '2026-10-02', 'day_of_week' => 5, 'value' => 0.0],
            ['date' => '2026-10-03', 'day_of_week' => 6, 'value' => 0.0],
            ['date' => '2026-10-04', 'day_of_week' => 7, 'value' => 0.0],
            ['date' => '2026-10-05', 'day_of_week' => 1, 'value' => 0.0],
            ['date' => '2026-10-06', 'day_of_week' => 2, 'value' => 0.0],
            ['date' => '2026-10-07', 'day_of_week' => 3, 'value' => 0.0],
            // Test points (all zero)
            ['date' => '2026-10-08', 'day_of_week' => 4, 'value' => 0.0],
            ['date' => '2026-10-09', 'day_of_week' => 5, 'value' => 0.0],
        ];

        $eval = $this->evaluator->evaluateTemporal(
            engine: $this->engine,
            fullSeries: $series,
            splitDate: '2026-10-07',
            horizonDays: 2
        );

        $this->assertEquals(0.0, $eval->mae);
        $this->assertEquals(0.0, $eval->rmse);
        $this->assertNull($eval->wape); // Div/0 avoided cleanly
    }
}
