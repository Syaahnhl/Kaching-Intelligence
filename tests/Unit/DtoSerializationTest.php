<?php

namespace Kaching\Intelligence\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Kaching\Intelligence\DTOs\ActorContextDTO;
use Kaching\Intelligence\DTOs\ActionProposalDTO;
use Kaching\Intelligence\DTOs\FinancialMetricsDTO;
use Kaching\Intelligence\DTOs\IntelligenceResponseDTO;
use Kaching\Intelligence\DTOs\InventorySnapshotDTO;
use Kaching\Intelligence\DTOs\RestockRecommendationDTO;
use Kaching\Intelligence\DTOs\StockoutRiskDTO;

class DtoSerializationTest extends TestCase
{
    public function test_actor_context_dto_instantiation_and_toArray(): void
    {
        $dto = new ActorContextDTO(
            userId: 1,
            outletId: 2,
            role: 'owner',
            planTier: 'pro',
            locale: 'id'
        );

        $this->assertSame(1, $dto->userId);
        $this->assertSame(2, $dto->outletId);
        $this->assertSame('owner', $dto->role);
        $this->assertSame('pro', $dto->planTier);
        $this->assertSame('id', $dto->locale);

        $array = $dto->toArray();
        $this->assertSame(1, $array['user_id']);
        $this->assertSame(2, $array['outlet_id']);
        $this->assertSame('owner', $array['role']);
        $this->assertSame('pro', $array['plan_tier']);
        $this->assertSame('id', $array['locale']);
    }

    public function test_action_proposal_dto_governance_invariants(): void
    {
        $proposal = new ActionProposalDTO(
            proposalId: 'prop_test_123',
            status: 'PENDING',
            action: 'adjust_stock',
            riskLevel: 'medium',
            approvalRequired: true,
            requiredRole: 'owner',
            reversibility: 'reversible',
            reason: 'Koreksi stok harian',
            parameters: ['menu_id' => 10, 'qty' => 5],
            expiresAt: '2026-10-31T23:59:59Z'
        );

        $this->assertTrue($proposal->approvalRequired);
        $this->assertSame('PENDING', $proposal->status);
        $this->assertSame('adjust_stock', $proposal->action);

        $array = $proposal->toArray();
        $this->assertSame('prop_test_123', $array['proposal_id']);
        $this->assertTrue($array['approval_required']);
        $this->assertSame('PENDING', $array['status']);
    }

    public function test_inventory_snapshot_and_advisory_dtos(): void
    {
        $snapshot = new InventorySnapshotDTO(
            itemId: 101,
            itemName: 'Kopi Robusta',
            sku: 'SKU-101',
            unit: 'kg',
            currentStock: 15.0,
            minimumStock: 5.0,
            safetyStock: 2.0,
            costPrice: 50000.0,
            outletId: 2,
            category: 'beverage'
        );

        $this->assertSame(101, $snapshot->itemId);
        $this->assertSame('Kopi Robusta', $snapshot->itemName);
        $this->assertSame(15.0, $snapshot->currentStock);

        $risk = new StockoutRiskDTO(
            menuId: 101,
            menuName: 'Signature Latte',
            riskLevel: 'HIGH',
            daysRemaining: 1.2,
            criticalIngredients: ['Susu Segar'],
            reasons: ['Stok susu menipis'],
            outletId: 2
        );

        $this->assertSame('HIGH', $risk->riskLevel);
        $this->assertSame(1.2, $risk->daysRemaining);
    }
}
