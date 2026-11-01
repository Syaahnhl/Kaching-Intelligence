<?php

declare(strict_types=1);

namespace Kaching\Intelligence\DTOs;

/**
 * Context DTO for External Business Radar Synthesis.
 */
final class BusinessRadarContextDTO
{
    /**
     * @param int $userId Tenant owner
     * @param int|null $outletId
     * @param string $category Signal category (all, raw_material, purchasing_power, local_event)
     * @param array<string, mixed> $internalFinancialContext Summary of store metrics (e.g. recent margin, primary ingredients)
     * @param array<string, mixed> $options
     */
    public function __construct(
        public readonly int $userId,
        public readonly ?int $outletId,
        public readonly string $category,
        public readonly array $internalFinancialContext = [],
        public readonly array $options = []
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            userId: (int) ($data['user_id'] ?? 0),
            outletId: isset($data['outlet_id']) ? (int) $data['outlet_id'] : null,
            category: (string) ($data['category'] ?? 'all'),
            internalFinancialContext: (array) ($data['internal_financial_context'] ?? []),
            options: (array) ($data['options'] ?? [])
        );
    }
}
