<?php

declare(strict_types=1);

namespace Kaching\Intelligence\Tests\Unit;

use Kaching\Intelligence\DTOs\CashFlowForecastContextDTO;
use Kaching\Intelligence\DTOs\CashFlowTimeSeriesPointDTO;
use Kaching\Intelligence\DTOs\MenuItemAggregateDTO;
use Kaching\Intelligence\DTOs\MenuBusinessAnalyticsContextDTO;
use Kaching\Intelligence\DTOs\BusinessRadarContextDTO;
use Kaching\Intelligence\Financial\CashFlowForecastingEngine;
use Kaching\Intelligence\Financial\MenuBusinessAnalyticsEngine;
use Kaching\Intelligence\Financial\BusinessRadarEngine;
use Kaching\Intelligence\Contracts\CommodityPriceProviderInterface;
use Kaching\Intelligence\Contracts\WeatherProviderInterface;
use Kaching\Intelligence\DTOs\CommodityResultDTO;
use Kaching\Intelligence\DTOs\CommodityItemDTO;
use Kaching\Intelligence\DTOs\WeatherResultDTO;
use Kaching\Intelligence\DTOs\WeatherItemDTO;
use PHPUnit\Framework\TestCase;

class FinancialAnalyticsEnginesUnitTest extends TestCase
{
    public function testCashFlowForecastingDowSeasonalDeterministic(): void
    {
        $engine = new CashFlowForecastingEngine();

        // Build 28 days of regular history (4 weeks) with high Friday/Saturday sales
        $historySeries = [];
        $anchorDate = '2026-10-31'; // Saturday
        for ($i = 27; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days", strtotime($anchorDate)));
            $dow = (int) date('N', strtotime($date));
            // Weekend multiplier
            $inflow = ($dow >= 5) ? 2000000.0 : 1000000.0;
            $outflow = 300000.0;

            $historySeries[] = new CashFlowTimeSeriesPointDTO(
                date: $date,
                inflow: $inflow,
                outflow: $outflow,
                transactionCount: 20
            );
        }

        $context = new CashFlowForecastContextDTO(
            userId: 1,
            outletId: 2,
            anchorDate: $anchorDate,
            timezone: 'Asia/Jakarta',
            horizonDays: 7,
            totalHistoryDays: 35,
            historySeries: $historySeries
        );

        $result1 = $engine->forecast($context);
        $result2 = $engine->forecast($context);

        $this->assertSame('SUCCESS', $result1->status);
        $this->assertSame(CashFlowForecastingEngine::MODEL_DOW_SEASONAL, $result1->model);
        $this->assertSame(CashFlowForecastingEngine::DQ_SUFFICIENT, $result1->dataQualityState);
        $this->assertCount(7, $result1->dailyForecast);

        // Determinism test: identical inputs yield identical numbers
        $this->assertSame($result1->projectedTotalInflow, $result2->projectedTotalInflow);
        $this->assertSame($result1->projectedNetOperatingCashFlow, $result2->projectedNetOperatingCashFlow);

        // Verify Sunday (day 1 after anchor Saturday) has weekend inflow 2,000,000
        $this->assertEquals(2000000.0, $result1->dailyForecast[0]['projected_inflow']);
        $this->assertEquals(300000.0, $result1->dailyForecast[0]['projected_outflow']);
        $this->assertEquals(1700000.0, $result1->dailyForecast[0]['projected_net_operating_cash_flow']);
    }

    public function testCashFlowForecastingWmaFallbackOnModerateHistory(): void
    {
        $engine = new CashFlowForecastingEngine();

        $historySeries = [];
        $anchorDate = '2026-10-31';
        for ($i = 13; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days", strtotime($anchorDate)));
            $historySeries[] = new CashFlowTimeSeriesPointDTO(
                date: $date,
                inflow: 1000000.0,
                outflow: 200000.0,
                transactionCount: 10
            );
        }

        $context = new CashFlowForecastContextDTO(
            userId: 1,
            outletId: 2,
            anchorDate: $anchorDate,
            timezone: 'Asia/Jakarta',
            horizonDays: 7,
            totalHistoryDays: 16, // Between 14 and 27
            historySeries: $historySeries
        );

        $result = $engine->forecast($context);

        $this->assertSame('SUCCESS', $result->status);
        $this->assertSame(CashFlowForecastingEngine::MODEL_WMA14, $result->model);
        $this->assertSame(CashFlowForecastingEngine::DQ_MODERATE_HISTORY, $result->dataQualityState);
        $this->assertGreaterThan(0.0, $result->projectedTotalInflow);
    }

    public function testCashFlowForecastingFailsClosedOnInsufficientHistoryOrZero(): void
    {
        $engine = new CashFlowForecastingEngine();

        // 1. History < 14 days
        $shortContext = new CashFlowForecastContextDTO(
            userId: 1,
            outletId: null,
            anchorDate: '2026-10-31',
            timezone: 'Asia/Jakarta',
            horizonDays: 7,
            totalHistoryDays: 5,
            historySeries: []
        );

        $shortResult = $engine->forecast($shortContext);
        $this->assertSame(CashFlowForecastingEngine::DQ_INSUFFICIENT_HISTORY, $shortResult->status);
        $this->assertSame(0.0, $shortResult->projectedTotalInflow);

        // 2. Zero baseline across 28 days
        $historySeries = [];
        for ($i = 27; $i >= 0; $i--) {
            $historySeries[] = new CashFlowTimeSeriesPointDTO(
                date: date('Y-m-d', strtotime("-{$i} days")),
                inflow: 0.0,
                outflow: 0.0
            );
        }

        $zeroContext = new CashFlowForecastContextDTO(
            userId: 1,
            outletId: null,
            anchorDate: date('Y-m-d'),
            timezone: 'Asia/Jakarta',
            horizonDays: 7,
            totalHistoryDays: 30,
            historySeries: $historySeries
        );

        $zeroResult = $engine->forecast($zeroContext);
        $this->assertSame(CashFlowForecastingEngine::DQ_ZERO_BASELINE, $zeroResult->status);
        $this->assertSame(0.0, $zeroResult->projectedTotalInflow);
    }

    public function testMenuBusinessAnalyticsBcgQuadrantsAndSawScoring(): void
    {
        $engine = new MenuBusinessAnalyticsEngine();

        // Create 4 distinct menu profiles
        $items = [
            // High Qty, High Profit -> STAR
            new MenuItemAggregateDTO(menuId: 1, name: 'Ayam Bakar Madu', category: 'Makanan', currentSellingPrice: 30000, costPrice: 15000, totalQty: 100, popularity: 50),
            // High Qty, Low Profit -> CASH COW
            new MenuItemAggregateDTO(menuId: 2, name: 'Es Teh Manis', category: 'Minuman', currentSellingPrice: 5000, costPrice: 4000, totalQty: 120, popularity: 60),
            // Low Qty, High Profit -> PUZZLE
            new MenuItemAggregateDTO(menuId: 3, name: 'Steak Wagyu', category: 'Makanan', currentSellingPrice: 150000, costPrice: 50000, totalQty: 10, popularity: 5),
            // Low Qty, Low Profit -> DOG
            new MenuItemAggregateDTO(menuId: 4, name: 'Krupuk Kaleng', category: 'Snack', currentSellingPrice: 2000, costPrice: 1800, totalQty: 5, popularity: 2),
        ];

        $context = new MenuBusinessAnalyticsContextDTO(
            userId: 1,
            outletId: 2,
            startDate: '2026-10-01',
            endDate: '2026-10-30',
            periodLabel: '30 Hari Terakhir',
            menuAggregates: $items
        );

        $result = $engine->analyze($context);

        $this->assertSame('SUCCESS', $result->status);
        $this->assertSame(4, $result->totalMenusAnalyzed);
        $this->assertNotEmpty($result->bcgQuadrants['STAR']['items']);
        $this->assertNotEmpty($result->bcgQuadrants['CASH_COW']['items']);
        $this->assertNotEmpty($result->bcgQuadrants['PUZZLE']['items']);
        $this->assertNotEmpty($result->bcgQuadrants['DOG']['items']);

        // Check SAW ordering
        $this->assertCount(4, $result->rankedMenuItems);
        $this->assertGreaterThanOrEqual($result->rankedMenuItems[1]['score'], $result->rankedMenuItems[0]['score']);
    }

    public function testMenuBusinessAnalyticsEmptyData(): void
    {
        $engine = new MenuBusinessAnalyticsEngine();
        $context = new MenuBusinessAnalyticsContextDTO(
            userId: 1,
            outletId: null,
            startDate: '2026-10-01',
            endDate: '2026-10-30',
            periodLabel: '30 Hari Terakhir',
            menuAggregates: []
        );

        $result = $engine->analyze($context);
        $this->assertSame('INSUFFICIENT_DATA', $result->status);
        $this->assertSame(0, $result->totalMenusAnalyzed);
        $this->assertEmpty($result->rankedMenuItems);
    }

    public function testBusinessRadarEngineSynthesisWithProviders(): void
    {
        $mockCommodity = $this->createMock(CommodityPriceProviderInterface::class);
        $mockCommodity->method('fetchPrices')->willReturn(
            new CommodityResultDTO(
                success: true,
                status: CommodityResultDTO::STATUS_SUCCESS,
                providerIdentifier: 'Bapanas',
                sourceAttribution: 'Badan Pangan Nasional',
                items: [
                    new CommodityItemDTO(
                        commodityCode: 'MINYAK_GORENG',
                        commodityName: 'Minyak Goreng Curah',
                        price: 18500.0,
                        currency: 'IDR',
                        rawUom: 'kg',
                        standardUom: 'kg',
                        standardPricePerUom: 18500.0,
                        category: 'Minyak',
                        marketRegion: 'Jawa Barat',
                        priceLevel: 'PROVINSI',
                        observationDate: '2026-10-31',
                        retrievalTimestamp: '2026-10-31 08:00:00',
                        sourceIdentifier: 'Bapanas',
                        sourceAttribution: 'Badan Pangan Nasional',
                        dataQuality: 'VERIFIED'
                    )
                ]
            )
        );

        $mockWeather = $this->createMock(WeatherProviderInterface::class);
        $mockWeather->method('fetchWeather')->willReturn(
            new WeatherResultDTO(
                success: true,
                status: WeatherResultDTO::STATUS_SUCCESS,
                providerIdentifier: 'Open-Meteo',
                sourceAttribution: 'Open-Meteo Forecast API',
                current: new WeatherItemDTO(
                    latitude: -6.2,
                    longitude: 106.8,
                    timestamp: '2026-10-31 09:00:00',
                    temperatureC: 24.5,
                    precipitationMm: 2.5,
                    weatherCondition: 'Hujan Ringan',
                    weatherCode: 61,
                    windSpeedKmh: 12.0,
                    relativeHumidityPercent: 85
                )
            )
        );

        $engine = new BusinessRadarEngine($mockCommodity, $mockWeather);
        $context = new BusinessRadarContextDTO(
            userId: 1,
            outletId: 2,
            category: 'all'
        );

        $result = $engine->inspect($context);

        $this->assertSame('SUCCESS', $result->status);
        $this->assertCount(2, $result->signals);
        $this->assertStringContainsString('Pangan', $result->signals[0]['source']);
        $this->assertStringContainsString('Open-Meteo', $result->signals[1]['source']);
        $this->assertNotEmpty($result->recommendations);
    }
}
