<?php

declare(strict_types=1);

namespace Kaching\Intelligence\Orchestration;

use Kaching\Intelligence\Contracts\IntelligenceContextAssemblerInterface;
use Kaching\Intelligence\DTOs\ActorContextDTO;
use Kaching\Intelligence\DTOs\CashFlowForecastContextDTO;
use Kaching\Intelligence\DTOs\DemandForecastContextDTO;
use Kaching\Intelligence\DTOs\ForecastHorizonDTO;
use Kaching\Intelligence\DTOs\MenuBusinessAnalyticsContextDTO;
use Kaching\Intelligence\DTOs\MenuRecipeContextDTO;
use Kaching\Intelligence\DTOs\SafetyStockContextDTO;
use Kaching\Intelligence\DTOs\UnifiedIntelligenceRequestDTO;

class IntelligenceContextAssembler implements IntelligenceContextAssemblerInterface
{
    /**
     * @var array<string, callable> Callbacks provided by Core Adapters to load domain contexts
     */
    protected array $contextLoaders = [];

    public function registerLoader(string $domain, callable $loader): void
    {
        $this->contextLoaders[$domain] = $loader;
    }

    /**
     * Assemble required POPO DTO contexts for target domains.
     *
     * @param UnifiedIntelligenceRequestDTO $request
     * @param array<int, string> $targetDomains
     * @param array<string, mixed> $entities
     * @return array<string, mixed> Assembled contexts keyed by domain name
     */
    public function assemble(UnifiedIntelligenceRequestDTO $request, array $targetDomains, array $entities): array
    {
        $assembled = [];

        foreach ($targetDomains as $domain) {
            if (isset($this->contextLoaders[$domain])) {
                $assembled[$domain] = call_user_func($this->contextLoaders[$domain], $request, $entities);
            } else {
                // Default synthesis if no specific external loader attached
                $assembled[$domain] = $this->synthesizeDefaultContext($domain, $request, $entities);
            }
        }

        return $assembled;
    }

    protected function synthesizeDefaultContext(string $domain, UnifiedIntelligenceRequestDTO $request, array $entities): mixed
    {
        $horizonDays = (int) ($entities['horizon_days'] ?? $request->extraParameters['horizon_days'] ?? 7);
        $itemName = (string) ($entities['item_name'] ?? $entities['commodity'] ?? $request->extraParameters['item_name'] ?? 'Item');

        switch ($domain) {
            case 'raw_material':
                return [
                    'recipes' => $request->extraParameters['recipes'] ?? [],
                    'commodity' => $entities['commodity'] ?? null,
                ];

            case 'demand_forecast':
                $startDate = date('Y-m-d', strtotime('+1 day'));
                $endDate = date('Y-m-d', strtotime("+{$horizonDays} days"));
                $horizon = new ForecastHorizonDTO($startDate, $endDate, $horizonDays);

                return new DemandForecastContextDTO(
                    itemIdentifier: $itemName,
                    itemName: ucfirst($itemName),
                    category: 'General',
                    uom: 'pcs',
                    outletIdentifier: $request->actor->outletId !== null ? (string)$request->actor->outletId : 'outlet-default',
                    cutoffDate: date('Y-m-d'),
                    horizon: $horizon,
                    historicalSeries: $request->extraParameters['historical_series'] ?? []
                );

            case 'safety_stock':
                return new SafetyStockContextDTO(
                    itemIdentifier: $itemName,
                    itemName: ucfirst($itemName),
                    uom: 'kg',
                    averageDailyDemand: (float) ($request->extraParameters['average_daily_demand'] ?? 10.0),
                    demandStdDev: (float) ($request->extraParameters['demand_std_dev'] ?? 2.5),
                    leadTimeDays: (float) ($request->extraParameters['lead_time_days'] ?? 2.0),
                    leadTimeStdDev: (float) ($request->extraParameters['lead_time_std_dev'] ?? 0.5),
                    targetServiceLevel: (float) ($request->extraParameters['service_level'] ?? 0.95),
                    currentStockOnHand: (float) ($request->extraParameters['current_stock'] ?? 15.0)
                );

            case 'cash_flow':
                return new CashFlowForecastContextDTO(
                    userId: $request->actor->userId,
                    outletId: $request->actor->outletId,
                    anchorDate: (string) ($request->extraParameters['anchor_date'] ?? date('Y-m-d')),
                    timezone: (string) ($request->extraParameters['timezone'] ?? 'Asia/Jakarta'),
                    horizonDays: $horizonDays,
                    totalHistoryDays: (int) ($request->extraParameters['total_history_days'] ?? 30),
                    historySeries: $request->extraParameters['historical_series'] ?? [],
                    metadata: $request->extraParameters
                );

            case 'menu_analytics':
                return new MenuBusinessAnalyticsContextDTO(
                    userId: $request->actor->userId,
                    outletId: $request->actor->outletId,
                    startDate: date('Y-m-d', strtotime('-30 days')),
                    endDate: date('Y-m-d'),
                    periodLabel: '30 Hari Terakhir',
                    menuAggregates: $request->extraParameters['menu_items'] ?? []
                );

            default:
                return [];
        }
    }
}
