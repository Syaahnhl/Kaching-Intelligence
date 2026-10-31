<?php

namespace Kaching\Intelligence\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Kaching\Intelligence\Services\KachingIntelligenceEngine;
use Kaching\Intelligence\DTOs\ActorContextDTO;
use Kaching\Intelligence\Tests\Fakes\FakeProposalSink;
use Kaching\Intelligence\Tests\Fakes\FakeFinancialDataProvider;
use Kaching\Intelligence\Tests\Fakes\FakeInventoryDataProvider;

class EngineInstantiationAndInventoryForwardingTest extends TestCase
{
    public function test_engine_instantiation_and_advisory_forwarding(): void
    {
        $proposalSink = new FakeProposalSink();
        $financialProvider = new FakeFinancialDataProvider();
        $inventoryProvider = new FakeInventoryDataProvider();

        $engine = new KachingIntelligenceEngine(
            proposalSink: $proposalSink,
            financialDataProvider: $financialProvider,
            inventoryDataProvider: $inventoryProvider
        );

        $context = new ActorContextDTO(userId: 10, outletId: 20);

        // Test Inventory advisory forwarding
        $risks = $engine->getStockoutRisks($context);
        $this->assertIsArray($risks);
        $this->assertCount(1, $risks);
        $this->assertSame('Signature Roast Coffee', $risks[0]->menuName);

        $recommendations = $engine->getRestockRecommendations($context);
        $this->assertIsArray($recommendations);
        $this->assertCount(1, $recommendations);
        $this->assertSame('Arabica Coffee Beans', $recommendations[0]->itemName);
    }

    public function test_engine_proposal_submission_is_delegated_to_sink(): void
    {
        $proposalSink = new FakeProposalSink();
        $financialProvider = new FakeFinancialDataProvider();

        $engine = new KachingIntelligenceEngine(
            proposalSink: $proposalSink,
            financialDataProvider: $financialProvider
        );

        $proposal = $engine->submitActionProposal(
            userId: 1,
            outletId: 2,
            action: 'create_purchase_order',
            parameters: ['supplier_id' => 99, 'amount' => 500000]
        );

        $this->assertSame('create_purchase_order', $proposal->action);
        $this->assertTrue($proposal->approvalRequired);
        $this->assertCount(1, $proposalSink->proposals);
    }
}
