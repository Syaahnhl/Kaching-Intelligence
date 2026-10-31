<?php

namespace Kaching\Intelligence\Tests\Fakes;

use Kaching\Intelligence\Contracts\InventoryDataProviderInterface;
use Kaching\Intelligence\DTOs\InventorySnapshotDTO;
use Kaching\Intelligence\DTOs\StockoutRiskDTO;
use Kaching\Intelligence\DTOs\RestockRecommendationDTO;

class FakeInventoryDataProvider implements InventoryDataProviderInterface
{
    /** @var array<int, array<string, mixed>> */
    protected array $records = [];

    public function seed(int $userId, ?int $outletId, array $items): void
    {
        $this->records[] = [
            'user_id' => $userId,
            'outlet_id' => $outletId,
            'items' => $items,
        ];
    }

    public function getInventorySnapshots(int $userId, ?int $outletId = null): array
    {
        $tenantItems = $this->filterTenant($userId, $outletId);

        $dtos = [];
        foreach ($tenantItems as $item) {
            $dtos[] = new InventorySnapshotDTO(
                itemId: $item['id'],
                itemName: $item['name'],
                sku: 'SKU-' . $item['id'],
                unit: 'pcs',
                currentStock: (float) $item['stock'],
                minimumStock: 5.0,
                safetyStock: 2.0,
                costPrice: 15000.0,
                outletId: $outletId,
                category: 'beverage'
            );
        }

        return $dtos;
    }

    public function getStockoutRisks(int $userId, ?int $outletId = null, ?int $menuId = null): array
    {
        $this->filterTenant($userId, $outletId);

        return [
            new StockoutRiskDTO(
                menuId: $menuId ?? 101,
                menuName: 'Signature Roast Coffee',
                riskLevel: 'CRITICAL',
                daysRemaining: 1.5,
                criticalIngredients: ['Biji Kopi'],
                reasons: ['Stok tersisa di bawah ambang batas']
            ),
        ];
    }

    public function getRestockRecommendations(int $userId, ?int $outletId = null, array $options = []): array
    {
        $this->filterTenant($userId, $outletId);

        return [
            new RestockRecommendationDTO(
                itemId: 501,
                itemName: 'Arabica Coffee Beans',
                currentStock: 2.0,
                recommendedQty: 25.0,
                unit: 'kg',
                estimatedCost: 1750000.0,
                priority: 'HIGH',
                reasons: ['Stockout predicted in 36 hours based on recent demand velocity']
            ),
        ];
    }

    protected function filterTenant(int $userId, ?int $outletId): array
    {
        $matched = [];
        foreach ($this->records as $record) {
            if ($record['user_id'] === $userId && ($outletId === null || $record['outlet_id'] === $outletId)) {
                $matched = array_merge($matched, $record['items']);
            }
        }
        return $matched;
    }
}
