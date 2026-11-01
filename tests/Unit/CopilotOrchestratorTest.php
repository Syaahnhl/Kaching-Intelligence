<?php

declare(strict_types=1);

namespace Kaching\Intelligence\Tests\Unit;

use Kaching\Intelligence\Contracts\CashFlowForecastingEngineInterface;
use Kaching\Intelligence\Contracts\DemandForecastingEngineInterface;
use Kaching\Intelligence\Contracts\MenuBusinessAnalyticsEngineInterface;
use Kaching\Intelligence\Contracts\SafetyStockOptimizerInterface;
use Kaching\Intelligence\DTOs\ActorContextDTO;
use Kaching\Intelligence\DTOs\CashFlowForecastContextDTO;
use Kaching\Intelligence\DTOs\ForecastResultDTO;
use Kaching\Intelligence\DTOs\DemandForecastContextDTO;
use Kaching\Intelligence\DTOs\DemandForecastResultDTO;
use Kaching\Intelligence\DTOs\ForecastHorizonDTO;
use Kaching\Intelligence\DTOs\ForecastPointDTO;
use Kaching\Intelligence\DTOs\MenuIngredientContextDTO;
use Kaching\Intelligence\DTOs\MenuBusinessAnalyticsContextDTO;
use Kaching\Intelligence\DTOs\MenuBusinessAnalyticsResultDTO;
use Kaching\Intelligence\DTOs\MenuRecipeContextDTO;
use Kaching\Intelligence\DTOs\ModelMetadataDTO;
use Kaching\Intelligence\DTOs\SafetyStockContextDTO;
use Kaching\Intelligence\DTOs\SafetyStockRecommendationDTO;
use Kaching\Intelligence\DTOs\UnifiedIntelligenceRequestDTO;
use Kaching\Intelligence\Exceptions\TenantContextMismatchException;
use Kaching\Intelligence\Orchestration\CopilotOrchestrator;
use Kaching\Intelligence\Orchestration\DeterministicIntentClassifier;
use Kaching\Intelligence\Orchestration\IntelligenceContextAssembler;
use Kaching\Intelligence\Orchestration\IntelligenceResultAggregator;
use Kaching\Intelligence\Orchestration\MultiDomainExecutionCoordinator;
use Kaching\Intelligence\Orchestration\PolicyDomainRouter;
use Kaching\Intelligence\Contracts\CommodityPriceProviderInterface;
use Kaching\Intelligence\RawMaterial\CommodityClassifier;
use Kaching\Intelligence\RawMaterial\RawMaterialImpactEngine;
use Kaching\Intelligence\RawMaterial\UnitDimensionNormalizer;
use PHPUnit\Framework\TestCase;

class CopilotOrchestratorTest extends TestCase
{
    protected CopilotOrchestrator $orchestrator;
    protected DeterministicIntentClassifier $classifier;
    protected PolicyDomainRouter $router;
    protected IntelligenceContextAssembler $contextAssembler;
    protected MultiDomainExecutionCoordinator $executionCoordinator;
    protected IntelligenceResultAggregator $aggregator;

    protected RawMaterialImpactEngine $rawMaterialEngine;
    protected DemandForecastingEngineInterface $demandEngine;
    protected SafetyStockOptimizerInterface $safetyStockOptimizer;
    protected CashFlowForecastingEngineInterface $cashFlowEngine;
    protected MenuBusinessAnalyticsEngineInterface $menuAnalyticsEngine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->classifier = new DeterministicIntentClassifier();
        $this->router = new PolicyDomainRouter();
        $this->contextAssembler = new IntelligenceContextAssembler();

        // 1. Raw Material Engine
        $unitNormalizer = new UnitDimensionNormalizer();
        $commodityClassifier = new CommodityClassifier();
        $priceProvider = $this->createMock(CommodityPriceProviderInterface::class);
        $this->rawMaterialEngine = new RawMaterialImpactEngine($priceProvider, $unitNormalizer, $commodityClassifier);

        // 2. Mocks / Stubs for engines
        $this->demandEngine = $this->createMock(DemandForecastingEngineInterface::class);
        $this->safetyStockOptimizer = $this->createMock(SafetyStockOptimizerInterface::class);
        $this->cashFlowEngine = $this->createMock(CashFlowForecastingEngineInterface::class);
        $this->menuAnalyticsEngine = $this->createMock(MenuBusinessAnalyticsEngineInterface::class);

        $this->executionCoordinator = new MultiDomainExecutionCoordinator(
            rawMaterialEngine: $this->rawMaterialEngine,
            demandEngine: $this->demandEngine,
            safetyStockOptimizer: $this->safetyStockOptimizer,
            cashFlowEngine: $this->cashFlowEngine,
            menuAnalyticsEngine: $this->menuAnalyticsEngine
        );

        $this->aggregator = new IntelligenceResultAggregator();

        $this->orchestrator = new CopilotOrchestrator(
            $this->classifier,
            $this->router,
            $this->contextAssembler,
            $this->executionCoordinator,
            $this->aggregator
        );
    }

    public function testScenario1RawMaterialAndHppImpact(): void
    {
        $actor = new ActorContextDTO(userId: 10, outletId: 1, role: 'owner');
        $recipes = [
            new MenuRecipeContextDTO(
                menuId: 'm1',
                name: 'Ayam Geprek Sambal Korek',
                sellingPrice: 25000.0,
                recordedCostPrice: 12000.0,
                ingredients: [
                    new MenuIngredientContextDTO(1, 0.05, 'kg'),
                    new MenuIngredientContextDTO(2, 0.25, 'kg'),
                ]
            )
        ];

        $request = new UnifiedIntelligenceRequestDTO(
            message: 'Kalau harga cabai naik minggu depan, bagaimana dampaknya terhadap HPP menu saya?',
            actor: $actor,
            extraParameters: ['recipes' => $recipes]
        );

        $response = $this->orchestrator->process($request);

        $this->assertContains($response->status, ['SUCCESS', 'PARTIAL', 'FAILED']);
        $this->assertNotEmpty($response->headline);
        $this->assertTrue(
            str_contains($response->headline, 'HPP') || str_contains($response->headline, 'Bahan Baku')
        );
        $this->assertCount(1, $response->domainResults);
        $this->assertEquals('raw_material', $response->domainResults[0]->domain);
    }

    public function testScenario2DemandForecastAndSafetyStock(): void
    {
        $actor = new ActorContextDTO(userId: 10, outletId: 1, role: 'owner');
        $horizon = new ForecastHorizonDTO('2026-11-02', '2026-11-03', 2);

        $this->demandEngine->expects($this->once())
            ->method('forecast')
            ->willReturn(new DemandForecastResultDTO(
                itemIdentifier: 'ayam',
                outletIdentifier: '1',
                horizon: $horizon,
                forecastPoints: [
                    new ForecastPointDTO('2026-11-02', 1, 15.0, 10.0, 20.0),
                    new ForecastPointDTO('2026-11-03', 2, 18.0, 12.0, 24.0),
                ],
                totalProjectedDemand: 33.0,
                confidenceLevel: 'HIGH',
                historyStatus: 'SUFFICIENT',
                modelMetadata: new ModelMetadataDTO('DOW_MEDIAN', '1.0', 'dow_median_baseline', 'native-37f-v1', 'INTELLIGENCE_LOCAL', false, ['sample' => 14]),
                dataQualityWarnings: []
            ));

        $this->safetyStockOptimizer->expects($this->once())
            ->method('optimize')
            ->willReturn(new SafetyStockRecommendationDTO(
                itemIdentifier: 'ayam',
                itemName: 'Daging Ayam',
                uom: 'kg',
                averageDailyDemand: 10.0,
                leadTimeDays: 2.0,
                targetServiceLevel: 0.95,
                zScore: 1.645,
                leadTimeDemand: 30.0,
                safetyStock: 8.5,
                reorderPoint: 38.5,
                currentStockOnHand: 15.0,
                suggestedReorderQuantity: 25.0,
                isReorderRecommended: true,
                assumptions: ['Lead time demand normal'],
                warnings: []
            ));

        $request = new UnifiedIntelligenceRequestDTO(
            message: 'Berapa stok ayam yang harus saya siapkan minggu depan?',
            actor: $actor,
            requestedDomains: ['demand_forecast', 'safety_stock'],
            extraParameters: [
                'current_stock' => 15.0,
                'lead_time_days' => 2.0,
                'service_level' => 0.95,
            ]
        );

        $response = $this->orchestrator->process($request);

        $this->assertContains($response->status, ['SUCCESS', 'PARTIAL', 'DEGRADED']);
        $this->assertStringContainsString('Rekomendasi Stok', $response->headline);
        $this->assertCount(2, $response->domainResults);
        $this->assertNotEmpty($response->headline);
    }

    public function testScenario3CashflowAndDemandSimulation(): void
    {
        $actor = new ActorContextDTO(userId: 10, outletId: 1, role: 'owner');
        $horizon = new ForecastHorizonDTO('2026-11-02', '2026-11-02', 1);

        $this->demandEngine->expects($this->once())
            ->method('forecast')
            ->willReturn(new DemandForecastResultDTO(
                itemIdentifier: 'general_demand',
                outletIdentifier: '1',
                horizon: $horizon,
                forecastPoints: [
                    new ForecastPointDTO('2026-11-02', 1, 50.0, 30.0, 70.0),
                ],
                totalProjectedDemand: 50.0,
                confidenceLevel: 'HIGH',
                historyStatus: 'SUFFICIENT',
                modelMetadata: new ModelMetadataDTO('DOW_MEDIAN', '1.0', 'dow_median_baseline', 'native-37f-v1', 'INTELLIGENCE_LOCAL', false, []),
                dataQualityWarnings: []
            ));

        $this->cashFlowEngine->expects($this->once())
            ->method('forecast')
            ->willReturn(new ForecastResultDTO(
                status: 'SUCCESS',
                model: 'DAY_OF_WEEK_SEASONAL_BASELINE',
                dataQualityState: 'SUFFICIENT',
                horizonDays: 7,
                projectedTotalInflow: 5000000.0,
                projectedTotalOutflow: 5800000.0,
                projectedNetOperatingCashFlow: -800000.0,
                dailyForecast: [],
                qualityDetails: [],
                recommendations: ['Pertimbangkan kurangi belanja non-esensial'],
                message: 'Proyeksi arus kas defisit'
            ));

        $request = new UnifiedIntelligenceRequestDTO(
            message: 'Kalau penjualan minggu depan turun, bagaimana kondisi arus kas saya?',
            actor: $actor,
            extraParameters: ['current_balance' => 5000000.0]
        );

        $response = $this->orchestrator->process($request);

        $this->assertContains($response->status, ['SUCCESS', 'PARTIAL', 'DEGRADED']);
        $this->assertStringContainsString('Simulasi Arus Kas', $response->headline);
        $this->assertCount(2, $response->domainResults);
    }

    public function testScenario4MenuPerformanceAndMarginRisk(): void
    {
        $actor = new ActorContextDTO(userId: 10, outletId: 1, role: 'owner');

        $this->menuAnalyticsEngine->expects($this->once())
            ->method('analyze')
            ->willReturn(new MenuBusinessAnalyticsResultDTO(
                status: 'SUCCESS',
                totalMenusAnalyzed: 5,
                averageSalesVolume: 120.0,
                averageProfitContribution: 15000.0,
                bcgQuadrants: [],
                rankedMenuItems: [],
                strategicInsights: ['Evaluasi harga menu dengan kenaikan HPP']
            ));

        $request = new UnifiedIntelligenceRequestDTO(
            message: 'Menu mana yang perlu saya evaluasi karena margin-nya berisiko turun?',
            actor: $actor
        );

        $response = $this->orchestrator->process($request);

        $this->assertContains($response->status, ['SUCCESS', 'PARTIAL', 'FAILED']);
        $this->assertStringContainsString('Evaluasi Menu', $response->headline);
    }

    public function testScenario5GeneralQuestionSingleDomainRouting(): void
    {
        $actor = new ActorContextDTO(userId: 10, outletId: 1, role: 'cashier');

        $request = new UnifiedIntelligenceRequestDTO(
            message: 'Halo selamat siang, bantuan apa yang bisa diberikan?',
            actor: $actor
        );

        $response = $this->orchestrator->process($request);

        $this->assertEquals('SUCCESS', $response->status);
        $this->assertCount(1, $response->domainResults);
        $this->assertEquals('prompt', $response->domainResults[0]->domain);
    }

    public function testScenario6PartialFailureAndGracefulDegradation(): void
    {
        $actor = new ActorContextDTO(userId: 10, outletId: 1, role: 'owner');

        $this->demandEngine->expects($this->once())
            ->method('forecast')
            ->willThrowException(new \RuntimeException('Connection to inference server timed out'));

        $this->safetyStockOptimizer->expects($this->once())
            ->method('optimize')
            ->willReturn(new SafetyStockRecommendationDTO(
                itemIdentifier: 'ayam',
                itemName: 'Daging Ayam',
                uom: 'kg',
                averageDailyDemand: 10.0,
                leadTimeDays: 2.0,
                targetServiceLevel: 0.95,
                zScore: 1.645,
                leadTimeDemand: 15.0,
                safetyStock: 5.0,
                reorderPoint: 20.0,
                currentStockOnHand: 15.0,
                suggestedReorderQuantity: 15.0,
                isReorderRecommended: false,
                assumptions: [],
                warnings: []
            ));

        $request = new UnifiedIntelligenceRequestDTO(
            message: 'Berapa stok ayam yang harus saya siapkan minggu depan?',
            actor: $actor
        );

        $response = $this->orchestrator->process($request);

        $this->assertContains($response->status, ['DEGRADED', 'FAILED']);
        $this->assertCount(2, $response->domainResults);
        $this->assertNotEmpty($response->limitations);
    }

    public function testTenantContextSecurityValidation(): void
    {
        $invalidActor = new ActorContextDTO(userId: 0, outletId: 1, role: 'owner');

        $this->expectException(TenantContextMismatchException::class);

        $request = new UnifiedIntelligenceRequestDTO(
            message: 'Tampilkan analisis performa bisnis',
            actor: $invalidActor
        );

        $this->orchestrator->process($request);
    }

    public function testRoleBasedAccessControlFiltering(): void
    {
        // Cashier attempts to run cash flow and margin analysis
        $cashier = new ActorContextDTO(userId: 10, outletId: 1, role: 'cashier');

        $request = new UnifiedIntelligenceRequestDTO(
            message: 'Kalau penjualan minggu depan turun, bagaimana kondisi arus kas saya?',
            actor: $cashier
        );

        $classification = $this->classifier->classify($request->message, $cashier);
        $activeRoutes = $this->router->resolveRoutes($classification['domains'], $cashier);

        // Cash flow is denied for cashier; only demand_forecast is permitted
        $this->assertNotContains('cash_flow', $activeRoutes);
        $this->assertContains('demand_forecast', $activeRoutes);
    }
}
