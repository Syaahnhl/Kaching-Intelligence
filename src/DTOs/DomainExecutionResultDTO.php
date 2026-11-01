<?php

declare(strict_types=1);

namespace Kaching\Intelligence\DTOs;

final class DomainExecutionResultDTO
{
    /**
     * @param string $domain Domain name (e.g. raw_material, demand_forecast, cash_flow, menu_analytics, prompt)
     * @param string $status SUCCESS, FALLBACK, PARTIAL, FAILED, SKIPPED
     * @param array<string, mixed> $data Extracted/calculated domain payload
     * @param float $confidence Confidence score 0.0 - 1.0
     * @param string $strategy Strategy/model applied
     * @param array<string, mixed> $provenance Data origin, timestamps, record counts
     * @param array<int, string> $warnings Data quality or fallback warnings
     * @param array<int, ActionProposalDTO> $actionProposals Recommended actions
     * @param string|null $errorMessage Error message if failed
     */
    public function __construct(
        public readonly string $domain,
        public readonly string $status,
        public readonly array $data = [],
        public readonly float $confidence = 1.0,
        public readonly string $strategy = 'standard',
        public readonly array $provenance = [],
        public readonly array $warnings = [],
        public readonly array $actionProposals = [],
        public readonly ?string $errorMessage = null
    ) {
    }

    public function isSuccessful(): bool
    {
        return in_array($this->status, ['SUCCESS', 'FALLBACK', 'PARTIAL'], true);
    }

    public function toArray(): array
    {
        return [
            'domain' => $this->domain,
            'status' => $this->status,
            'data' => $this->data,
            'confidence' => $this->confidence,
            'strategy' => $this->strategy,
            'provenance' => $this->provenance,
            'warnings' => $this->warnings,
            'action_proposals' => array_map(fn($p) => method_exists($p, 'toArray') ? $p->toArray() : (array) $p, $this->actionProposals),
            'error_message' => $this->errorMessage,
        ];
    }
}
