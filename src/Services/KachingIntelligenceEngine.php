<?php

namespace Kaching\Intelligence\Services;

use Kaching\Intelligence\Contracts\IntelligenceEngineInterface;
use Kaching\Intelligence\Contracts\ProposalSinkInterface;
use Kaching\Intelligence\Contracts\FinancialDataProviderInterface;
use Kaching\Intelligence\Contracts\InventoryDataProviderInterface;
use Kaching\Intelligence\DTOs\ActorContextDTO;
use Kaching\Intelligence\DTOs\IntelligenceResponseDTO;
use Kaching\Intelligence\DTOs\ActionProposalDTO;
use Kaching\Intelligence\DTOs\RestockRecommendationDTO;
use Kaching\Intelligence\DTOs\StockoutRiskDTO;

/**
 * KachingIntelligenceEngine
 * 
 * Standalone, decoupled intelligence orchestration engine for Kaching POS.
 * Adheres strictly to the Proposer-Only pattern.
 */
class KachingIntelligenceEngine implements IntelligenceEngineInterface
{
    public function __construct(
        protected ProposalSinkInterface $proposalSink,
        protected FinancialDataProviderInterface $financialDataProvider,
        protected ?InventoryDataProviderInterface $inventoryDataProvider = null
    ) {
    }

    public function processMessage(ActorContextDTO $context, string $message, array $history = []): IntelligenceResponseDTO
    {
        return new IntelligenceResponseDTO(
            reply: 'Intelligence Engine processed message successfully.',
            status: 'success'
        );
    }

    public function submitActionProposal(int $userId, ?int $outletId, string $action, array $parameters, ?string $userRole = null): ActionProposalDTO
    {
        return $this->proposalSink->submitProposal($userId, $outletId, $action, $parameters, $userRole);
    }

    /**
     * Ambil rekomendasi restock untuk tenant terautentikasi (read-only advisory).
     *
     * @return RestockRecommendationDTO[]
     */
    public function getRestockRecommendations(ActorContextDTO $context, array $options = []): array
    {
        if ($this->inventoryDataProvider === null) {
            return [];
        }

        return $this->inventoryDataProvider->getRestockRecommendations(
            userId: $context->userId,
            outletId: $context->outletId,
            options: $options
        );
    }

    /**
     * Ambil evaluasi risiko kehabisan stok untuk tenant terautentikasi (read-only advisory).
     *
     * @return StockoutRiskDTO[]
     */
    public function getStockoutRisks(ActorContextDTO $context, ?int $menuId = null): array
    {
        if ($this->inventoryDataProvider === null) {
            return [];
        }

        return $this->inventoryDataProvider->getStockoutRisks(
            userId: $context->userId,
            outletId: $context->outletId,
            menuId: $menuId
        );
    }
}
