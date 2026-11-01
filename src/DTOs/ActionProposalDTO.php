<?php

namespace Kaching\Intelligence\DTOs;

readonly class ActionProposalDTO
{
    public function __construct(
        public string $proposalId,
        public string $status,
        public string $action,
        public string $riskLevel,
        public bool $approvalRequired,
        public string $requiredRole,
        public string $reversibility,
        public string $reason,
        public array $parameters,
        public string $expiresAt
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            proposalId: (string) ($data['proposal_id'] ?? ''),
            status: (string) ($data['status'] ?? 'PENDING'),
            action: (string) ($data['action'] ?? ''),
            riskLevel: (string) ($data['risk_level'] ?? 'medium'),
            approvalRequired: (bool) ($data['approval_required'] ?? true),
            requiredRole: (string) ($data['required_role'] ?? 'owner'),
            reversibility: (string) ($data['reversibility'] ?? 'reversible'),
            reason: (string) ($data['reason'] ?? ''),
            parameters: (array) ($data['parameters'] ?? []),
            expiresAt: (string) ($data['expires_at'] ?? '')
        );
    }

    public function toArray(): array
    {
        return [
            'proposal_id'       => $this->proposalId,
            'status'            => $this->status,
            'action'            => $this->action,
            'risk_level'        => $this->riskLevel,
            'approval_required' => $this->approvalRequired,
            'required_role'     => $this->requiredRole,
            'reversibility'     => $this->reversibility,
            'reason'            => $this->reason,
            'parameters'        => $this->parameters,
            'expires_at'        => $this->expiresAt,
        ];
    }
}
