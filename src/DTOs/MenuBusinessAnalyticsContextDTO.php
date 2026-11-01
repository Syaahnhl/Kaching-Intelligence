<?php

declare(strict_types=1);

namespace Kaching\Intelligence\DTOs;

/**
 * Context DTO for Menu Business Analytics and BCG Matrix Evaluation.
 */
final class MenuBusinessAnalyticsContextDTO
{
    /**
     * @param int $userId
     * @param int|null $outletId
     * @param string $startDate Evaluation start date (Y-m-d)
     * @param string $endDate Evaluation end date (Y-m-d)
     * @param string $periodLabel E.g., '30 Hari Terakhir'
     * @param array<MenuItemAggregateDTO> $menuAggregates
     * @param array<string, mixed> $options
     */
    public function __construct(
        public readonly int $userId,
        public readonly ?int $outletId,
        public readonly string $startDate,
        public readonly string $endDate,
        public readonly string $periodLabel,
        public readonly array $menuAggregates,
        public readonly array $options = []
    ) {
    }

    public static function fromArray(array $data): self
    {
        $items = [];
        foreach ($data['menu_aggregates'] ?? [] as $item) {
            if ($item instanceof MenuItemAggregateDTO) {
                $items[] = $item;
            } elseif (is_array($item)) {
                $items[] = MenuItemAggregateDTO::fromArray($item);
            }
        }

        return new self(
            userId: (int) ($data['user_id'] ?? 0),
            outletId: isset($data['outlet_id']) ? (int) $data['outlet_id'] : null,
            startDate: (string) ($data['start_date'] ?? ''),
            endDate: (string) ($data['end_date'] ?? ''),
            periodLabel: (string) ($data['period_label'] ?? 'Custom Window'),
            menuAggregates: $items,
            options: (array) ($data['options'] ?? [])
        );
    }
}
