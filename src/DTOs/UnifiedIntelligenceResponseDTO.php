<?php

declare(strict_types=1);

namespace Kaching\Intelligence\DTOs;

final class UnifiedIntelligenceResponseDTO
{
    /**
     * @param string $status SUCCESS, PARTIAL, DEGRADED, FAILED
     * @param string $headline Concise summary headline for UMKM user
     * @param string $narrative Comprehensive explanation / insight in Indonesian or English
     * @param array<int, DomainExecutionResultDTO> $domainResults Results grouped by executed domain
     * @param array<int, ActionProposalDTO> $actionProposals List of actionable proposals awaiting Core approval
     * @param array<int, string> $limitations Disclosed caveats, assumptions, or missing data warnings
     * @param float $overallConfidence Aggregate confidence score (0.0 to 1.0)
     * @param array<string, mixed> $telemetry Execution timing, engine count, route metadata
     */
    public function __construct(
        public readonly string $status,
        public readonly string $headline,
        public readonly string $narrative,
        public readonly array $domainResults = [],
        public readonly array $actionProposals = [],
        public readonly array $limitations = [],
        public readonly float $overallConfidence = 1.0,
        public readonly array $telemetry = []
    ) {
    }

    public function isSuccess(): bool
    {
        return in_array($this->status, ['SUCCESS', 'PARTIAL', 'DEGRADED'], true);
    }

    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'headline' => $this->headline,
            'narrative' => $this->narrative,
            'domain_results' => array_map(fn(DomainExecutionResultDTO $res) => $res->toArray(), $this->domainResults),
            'action_proposals' => array_map(fn(ActionProposalDTO $prop) => $prop->toArray(), $this->actionProposals),
            'limitations' => $this->limitations,
            'overall_confidence' => $this->overallConfidence,
            'telemetry' => $this->telemetry,
        ];
    }
}
