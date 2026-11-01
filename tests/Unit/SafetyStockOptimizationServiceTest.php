<?php

namespace Kaching\Intelligence\Tests\Unit;

use Kaching\Intelligence\DTOs\SafetyStockContextDTO;
use Kaching\Intelligence\DemandForecast\SafetyStockOptimizationService;
use PHPUnit\Framework\TestCase;

class SafetyStockOptimizationServiceTest extends TestCase
{
    private SafetyStockOptimizationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SafetyStockOptimizationService();
    }

    public function test_it_computes_accurate_z_scores(): void
    {
        // 95% -> approx 1.6449
        $z95 = $this->service->getZScore(0.95);
        $this->assertEqualsWithDelta(1.6449, $z95, 0.001);

        // 90% -> approx 1.2816
        $z90 = $this->service->getZScore(0.90);
        $this->assertEqualsWithDelta(1.2816, $z90, 0.001);

        // 99% -> approx 2.3263
        $z99 = $this->service->getZScore(0.99);
        $this->assertEqualsWithDelta(2.3263, $z99, 0.002);
    }

    public function test_it_optimizes_safety_stock_with_constant_lead_time(): void
    {
        // Example: daily demand = 20, sigma_d = 5, L = 4 days, sigma_L = 0, SL = 0.95 (Z = 1.6449)
        // LTD = 20 * 4 = 80
        // SS = 1.6449 * 5 * sqrt(4) = 1.6449 * 10 = 16.45
        // ROP = 80 + 16.45 = 96.45
        $context = new SafetyStockContextDTO(
            itemIdentifier: 'RAW_COFFEE_BEANS',
            itemName: 'Arabica Coffee Beans',
            uom: 'kg',
            averageDailyDemand: 20.0,
            demandStdDev: 5.0,
            leadTimeDays: 4.0,
            leadTimeStdDev: 0.0,
            targetServiceLevel: 0.95,
            currentStockOnHand: 90.0 // Below ROP (96.45)
        );

        $rec = $this->service->optimize($context);

        $this->assertEquals(80.0, $rec->leadTimeDemand);
        $this->assertEquals(16.45, $rec->safetyStock);
        $this->assertEquals(96.45, $rec->reorderPoint);
        $this->assertTrue($rec->isReorderRecommended);
        $this->assertGreaterThan(0.0, $rec->suggestedReorderQuantity);
    }

    public function test_it_optimizes_safety_stock_with_variable_lead_time(): void
    {
        // Example with variable lead time: sigma_L = 1.0 day
        // variance = (4 * 5^2) + (20^2 * 1^2) = (4 * 25) + (400 * 1) = 100 + 400 = 500
        // combinedStd = sqrt(500) = 22.3606
        // SS = 1.6449 * 22.3606 = 36.78
        // ROP = 80 + 36.78 = 116.78
        $context = new SafetyStockContextDTO(
            itemIdentifier: 'RAW_FRESH_MILK',
            itemName: 'Fresh Milk 1L',
            uom: 'liter',
            averageDailyDemand: 20.0,
            demandStdDev: 5.0,
            leadTimeDays: 4.0,
            leadTimeStdDev: 1.0,
            targetServiceLevel: 0.95,
            currentStockOnHand: 150.0 // Above ROP
        );

        $rec = $this->service->optimize($context);

        $this->assertEquals(80.0, $rec->leadTimeDemand);
        $this->assertEquals(36.79, $rec->safetyStock);
        $this->assertEquals(116.79, $rec->reorderPoint);
        $this->assertFalse($rec->isReorderRecommended);
        $this->assertEquals(0.0, $rec->suggestedReorderQuantity);
    }
}
