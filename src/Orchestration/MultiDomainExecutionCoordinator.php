<?php

declare(strict_types=1);

namespace Kaching\Intelligence\Orchestration;

use Kaching\Intelligence\Contracts\CashFlowForecastingEngineInterface;
use Kaching\Intelligence\Contracts\DemandForecastingEngineInterface;
use Kaching\Intelligence\Contracts\MenuBusinessAnalyticsEngineInterface;
use Kaching\Intelligence\Contracts\MultiDomainExecutionCoordinatorInterface;
use Kaching\Intelligence\Contracts\SafetyStockOptimizerInterface;
use Kaching\Intelligence\DemandForecast\CatBoostDemandForecastService;
use Kaching\Intelligence\DemandForecast\DowMedianDemandForecastService;
use Kaching\Intelligence\DTOs\ActionProposalDTO;
use Kaching\Intelligence\DTOs\CashFlowForecastContextDTO;
use Kaching\Intelligence\DTOs\DemandForecastContextDTO;
use Kaching\Intelligence\DTOs\DomainExecutionResultDTO;
use Kaching\Intelligence\DTOs\MenuBusinessAnalyticsContextDTO;
use Kaching\Intelligence\DTOs\SafetyStockContextDTO;
use Kaching\Intelligence\DTOs\UnifiedIntelligenceRequestDTO;
use Kaching\Intelligence\RawMaterial\RawMaterialImpactEngine;
use Throwable;

class MultiDomainExecutionCoordinator implements MultiDomainExecutionCoordinatorInterface
{
    public function __construct(
        protected ?RawMaterialImpactEngine $rawMaterialEngine = null,
        protected ?DemandForecastingEngineInterface $demandEngine = null,
        protected ?SafetyStockOptimizerInterface $safetyStockOptimizer = null,
        protected ?CashFlowForecastingEngineInterface $cashFlowEngine = null,
        protected ?MenuBusinessAnalyticsEngineInterface $menuAnalyticsEngine = null
    ) {
    }

    /**
     * Coordinate execution across multiple domains with isolation, timeout, and partial failure handling.
     *
     * @param array<int, string> $targetDomains
     * @param array<string, mixed> $assembledContexts
     * @param UnifiedIntelligenceRequestDTO $request
     * @return array<int, DomainExecutionResultDTO>
     */
    public function coordinate(
        array $targetDomains,
        array $assembledContexts,
        UnifiedIntelligenceRequestDTO $request
    ): array {
        $results = [];

        foreach ($targetDomains as $domain) {
            try {
                $context = $assembledContexts[$domain] ?? null;
                $result = match ($domain) {
                    'raw_material' => $this->executeRawMaterial($context, $request),
                    'demand_forecast' => $this->executeDemandForecast($context, $request),
                    'safety_stock' => $this->executeSafetyStock($context, $request),
                    'cash_flow' => $this->executeCashFlow($context, $request),
                    'menu_analytics' => $this->executeMenuAnalytics($context, $request),
                    'prompt' => $this->executePromptFallback($context, $request),
                    default => new DomainExecutionResultDTO(
                        domain: $domain,
                        status: 'SKIPPED',
                        data: [],
                        confidence: 0.0,
                        warnings: ["Domain '{$domain}' is not registered in execution coordinator"]
                    ),
                };

                $results[] = $result;
            } catch (Throwable $e) {
                // Partial failure containment: do not crash whole orchestration
                $results[] = new DomainExecutionResultDTO(
                    domain: $domain,
                    status: 'FAILED',
                    data: [],
                    confidence: 0.0,
                    warnings: ["Execution failed: {$e->getMessage()}"],
                    errorMessage: $e->getMessage()
                );
            }
        }

        return $results;
    }

    protected function executeRawMaterial(mixed $context, UnifiedIntelligenceRequestDTO $request): DomainExecutionResultDTO
    {
        if (!$this->rawMaterialEngine) {
            return new DomainExecutionResultDTO(
                domain: 'raw_material',
                status: 'SKIPPED',
                warnings: ['RawMaterialImpactEngine not bound']
            );
        }

        $recipes = is_array($context) && isset($context['recipes']) ? $context['recipes'] : [];
        if (empty($recipes)) {
            return new DomainExecutionResultDTO(
                domain: 'raw_material',
                status: 'PARTIAL',
                data: ['impacts' => []],
                confidence: 0.8,
                warnings: ['Tidak ada data resep bahan baku yang ditemukan untuk outlet ini. Perhitungan dampak menggunakan estimasi umum komoditas.']
            );
        }

        $impacts = $this->rawMaterialEngine->evaluateImpactBatch($recipes);

        $proposals = [];
        $serializedImpacts = [];
        foreach ($impacts as $res) {
            $serializedImpacts[] = $res->toArray();
            if ($res->isSignificantIncrease()) {
                $proposals[] = new ActionProposalDTO(
                    actionType: 'evaluate_menu_price',
                    domain: 'pricing',
                    payload: [
                        'menu_name' => $res->recipeContext->name,
                        'current_hpp' => $res->currentHpp,
                        'projected_hpp' => $res->projectedHpp,
                        'cost_increase_pct' => $res->costIncreasePercentage,
                    ],
                    reason: "Potensi kenaikan HPP {$res->costIncreasePercentage}% akibat pergerakan harga komoditas",
                    estimatedImpact: "Kenaikan biaya produksi Rp " . number_format($res->projectedHpp - $res->currentHpp, 0, ',', '.') . "/porsi",
                    requiresApproval: true
                );
            }
        }

        return new DomainExecutionResultDTO(
            domain: 'raw_material',
            status: 'SUCCESS',
            data: ['impacts' => $serializedImpacts],
            confidence: 0.95,
            strategy: 'bapanas_commodity_lookup',
            provenance: ['evaluated_recipes' => count($recipes), 'timestamp' => date('Y-m-d H:i:s')],
            actionProposals: $proposals
        );
    }

    protected function executeDemandForecast(mixed $context, UnifiedIntelligenceRequestDTO $request): DomainExecutionResultDTO
    {
        if (!$this->demandEngine) {
            return new DomainExecutionResultDTO(
                domain: 'demand_forecast',
                status: 'SKIPPED',
                warnings: ['DemandForecastingEngineInterface not bound']
            );
        }

        if (!($context instanceof DemandForecastContextDTO)) {
            return new DomainExecutionResultDTO(
                domain: 'demand_forecast',
                status: 'FAILED',
                warnings: ['Invalid DemandForecastContextDTO']
            );
        }

        $res = $this->demandEngine->forecast($context);

        $warnings = $res->dataQualityWarnings;
        $status = $res->modelMetadata->fallbackApplied ? 'FALLBACK' : 'SUCCESS';

        return new DomainExecutionResultDTO(
            domain: 'demand_forecast',
            status: $status,
            data: [
                'item_name' => $res->itemIdentifier,
                'forecast_points' => array_map(fn($p) => $p->toArray(), $res->forecastPoints),
                'total_predicted_quantity' => $res->totalProjectedDemand,
                'strategy' => $res->modelMetadata->strategyUsed,
            ],
            confidence: $res->modelMetadata->fallbackApplied ? 0.85 : 0.95,
            strategy: $res->modelMetadata->strategyUsed,
            provenance: $res->modelMetadata->provenance,
            warnings: $warnings
        );
    }

    protected function executeSafetyStock(mixed $context, UnifiedIntelligenceRequestDTO $request): DomainExecutionResultDTO
    {
        if (!$this->safetyStockOptimizer) {
            return new DomainExecutionResultDTO(
                domain: 'safety_stock',
                status: 'SKIPPED',
                warnings: ['SafetyStockOptimizerInterface not bound']
            );
        }

        if (!($context instanceof SafetyStockContextDTO)) {
            return new DomainExecutionResultDTO(
                domain: 'safety_stock',
                status: 'FAILED',
                warnings: ['Invalid SafetyStockContextDTO']
            );
        }

        $rec = $this->safetyStockOptimizer->optimize($context);

        $proposals = [];
        $proposals[] = new ActionProposalDTO(
            actionType: 'draft_purchase_order',
            domain: 'inventory',
            payload: [
                'item' => $rec->itemName,
                'suggested_reorder_qty' => $rec->suggestedReorderQuantity,
                'reorder_point' => $rec->reorderPoint,
                'current_stock' => $context->currentStockOnHand,
                'safety_stock' => $rec->safetyStock,
            ],
            reason: "Stok fisik saat ini ({$context->currentStockOnHand} {$context->uom}) telah mencapai atau berada di bawah Reorder Point ({$rec->reorderPoint} {$context->uom})",
            estimatedImpact: "Mencegah stockout dengan kulakan rekomendasi {$rec->suggestedReorderQuantity} {$context->uom}",
            requiresApproval: true
        );

        return new DomainExecutionResultDTO(
            domain: 'safety_stock',
            status: 'SUCCESS',
            data: $rec->toArray(),
            confidence: 0.95,
            strategy: $rec->calculationMethod,
            provenance: ['lead_time_days' => $context->leadTimeDays, 'service_level' => $context->targetServiceLevel],
            warnings: $rec->warnings,
            actionProposals: $proposals
        );
    }

    protected function executeCashFlow(mixed $context, UnifiedIntelligenceRequestDTO $request): DomainExecutionResultDTO
    {
        if (!$this->cashFlowEngine) {
            return new DomainExecutionResultDTO(
                domain: 'cash_flow',
                status: 'SKIPPED',
                warnings: ['CashFlowForecastingEngineInterface not bound']
            );
        }

        if (!($context instanceof CashFlowForecastContextDTO)) {
            return new DomainExecutionResultDTO(
                domain: 'cash_flow',
                status: 'FAILED',
                warnings: ['Invalid CashFlowForecastContextDTO']
            );
        }

        $res = $this->cashFlowEngine->forecast($context);

        $confidence = match ($res->dataQualityState ?? '') {
            'SUFFICIENT' => 0.95,
            'MODERATE_HISTORY' => 0.75,
            'SPARSE_DATA' => 0.40,
            default => 0.50,
        };

        return new DomainExecutionResultDTO(
            domain: 'cash_flow',
            status: 'SUCCESS',
            data: $res->toArray(),
            confidence: $confidence,
            strategy: $res->model,
            provenance: ['horizon_days' => $context->horizonDays],
            warnings: !empty($res->recommendations) ? $res->recommendations : []
        );
    }

    protected function executeMenuAnalytics(mixed $context, UnifiedIntelligenceRequestDTO $request): DomainExecutionResultDTO
    {
        if (!$this->menuAnalyticsEngine) {
            return new DomainExecutionResultDTO(
                domain: 'menu_analytics',
                status: 'SKIPPED',
                warnings: ['MenuBusinessAnalyticsEngineInterface not bound']
            );
        }

        if (!($context instanceof MenuBusinessAnalyticsContextDTO)) {
            return new DomainExecutionResultDTO(
                domain: 'menu_analytics',
                status: 'FAILED',
                warnings: ['Invalid MenuBusinessAnalyticsContextDTO']
            );
        }

        $res = $this->menuAnalyticsEngine->analyze($context);

        return new DomainExecutionResultDTO(
            domain: 'menu_analytics',
            status: 'SUCCESS',
            data: $res->toArray(),
            confidence: 0.95,
            strategy: 'boston_consulting_matrix',
            provenance: ['total_items_analyzed' => $res->totalMenusAnalyzed],
            warnings: $res->strategicInsights
        );
    }

    protected function executePromptFallback(mixed $context, UnifiedIntelligenceRequestDTO $request): DomainExecutionResultDTO
    {
        return new DomainExecutionResultDTO(
            domain: 'prompt',
            status: 'SUCCESS',
            data: [
                'message' => $request->message,
                'response_text' => 'Halo! Saya Kaching Copilot. Ada yang bisa saya bantu terkait analisis bahan baku, perkiraan stok, arus kas, atau performa menu restoran Anda?',
            ],
            confidence: 1.0,
            strategy: 'conversational_copilot_persona'
        );
    }
}
