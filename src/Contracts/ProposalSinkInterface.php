<?php

namespace Kaching\Intelligence\Contracts;

use Kaching\Intelligence\DTOs\ActionProposalDTO;

interface ProposalSinkInterface
{
    /**
     * Submit an action proposal for firewall evaluation and transient cache storage.
     * AI is Proposer only; Core is Executor.
     */
    public function submitProposal(int $userId, ?int $outletId, string $action, array $parameters, ?string $userRole = null): ActionProposalDTO;
}
