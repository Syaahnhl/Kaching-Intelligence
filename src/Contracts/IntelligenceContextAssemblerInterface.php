<?php

declare(strict_types=1);

namespace Kaching\Intelligence\Contracts;

use Kaching\Intelligence\DTOs\UnifiedIntelligenceRequestDTO;

interface IntelligenceContextAssemblerInterface
{
    /**
     * Assemble required POPO DTO contexts for target domains.
     *
     * @param UnifiedIntelligenceRequestDTO $request
     * @param array<int, string> $targetDomains
     * @param array<string, mixed> $entities
     * @return array<string, mixed> Assembled contexts keyed by domain name
     */
    public function assemble(UnifiedIntelligenceRequestDTO $request, array $targetDomains, array $entities): array;
}
