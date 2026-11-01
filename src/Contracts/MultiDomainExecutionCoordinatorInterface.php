<?php

declare(strict_types=1);

namespace Kaching\Intelligence\Contracts;

use Kaching\Intelligence\DTOs\DomainExecutionResultDTO;
use Kaching\Intelligence\DTOs\UnifiedIntelligenceRequestDTO;

interface MultiDomainExecutionCoordinatorInterface
{
    /**
     * Coordinate execution across multiple domains with isolation, timeout, and partial failure handling.
     *
     * @param array<int, string> $targetDomains
     * @param array<string, mixed> $assembledContexts
     * @param UnifiedIntelligenceRequestDTO $request
     * @return array<int, DomainExecutionResultDTO>
     */
    public function coordinate(
        array $targetDomains,
        array $assembledContexts,
        UnifiedIntelligenceRequestDTO $request
    ): array;
}
