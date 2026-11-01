<?php

namespace Kaching\Intelligence\Tests\Fakes;

use Kaching\Intelligence\Contracts\ProposalSinkInterface;
use Kaching\Intelligence\DTOs\ActionProposalDTO;

class FakeProposalSink implements ProposalSinkInterface
{
    /** @var ActionProposalDTO[] */
    public array $proposals = [];

    public function submitProposal(int $userId, ?int $outletId, string $action, array $parameters, ?string $userRole = null): ActionProposalDTO
    {
        $proposal = new ActionProposalDTO(
            proposalId: 'prop_' . uniqid(),
            status: 'PENDING',
            action: $action,
            riskLevel: 'medium',
            approvalRequired: true,
            requiredRole: $userRole ?? 'owner',
            reversibility: 'reversible',
            reason: 'Generated via FakeProposalSink',
            parameters: $parameters,
            expiresAt: date('c', time() + 900)
        );

        $this->proposals[] = $proposal;

        return $proposal;
    }
}
