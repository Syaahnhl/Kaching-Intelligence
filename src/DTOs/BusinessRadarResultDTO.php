<?php

declare(strict_types=1);

namespace Kaching\Intelligence\DTOs;

/**
 * Result DTO for external business radar signals and cross-domain market impacts.
 */
final class BusinessRadarResultDTO
{
    /**
     * @param string $status SUCCESS, NO_DATA, ERROR, LIMITATION
     * @param string $category Evaluated category
     * @param array<array{
     *     title: string,
     *     source: string,
     *     url?: string,
     *     published_at?: string,
     *     snippet: string,
     *     category: string,
     *     impact_direction: string,
     *     severity: string,
     *     evidence_attribution: string
     * }> $signals Verified external signals with attribution
     * @param array<string> $recommendations Actionable business suggestions based on evidence
     * @param array<string> $limitations Clear statement of missing data or limitations
     * @param array<string, mixed> $metadata Additional context metadata
     */
    public function __construct(
        public readonly string $status,
        public readonly string $category,
        public readonly array $signals,
        public readonly array $recommendations = [],
        public readonly array $limitations = [],
        public readonly array $metadata = []
    ) {
    }

    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'category' => $this->category,
            'signals' => $this->signals,
            'recommendations' => $this->recommendations,
            'limitations' => $this->limitations,
            'metadata' => $this->metadata,
        ];
    }
}
