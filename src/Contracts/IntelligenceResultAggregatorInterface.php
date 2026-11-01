<?php

declare(strict_types=1);

namespace Kaching\Intelligence\Contracts;

use Kaching\Intelligence\DTOs\DomainExecutionResultDTO;
use Kaching\Intelligence\DTOs\UnifiedIntelligenceRequestDTO;
use Kaching\Intelligence\DTOs\UnifiedIntelligenceResponseDTO;

interface IntelligenceResultAggregatorInterface
{
    /**
     * Aggregate domain execution results, merge action proposals, propagate limitations, and compose unified response.
     *
     * @param array<int, DomainExecutionResultDTO> $domainResults
     * @param UnifiedIntelligenceRequestDTO $request
     * @param array<string, mixed> $classification
     * @param float $executionDurationMs
     * @return UnifiedIntelligenceResponseDTO
     */
    public function aggregate(
        array $domainResults,
        UnifiedIntelligenceRequestDTO $request,
        array $classification,
        float $executionDurationMs
    ): UnifiedIntelligenceResponseDTO;
}
