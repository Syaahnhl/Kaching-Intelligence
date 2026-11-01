<?php

declare(strict_types=1);

namespace Kaching\Intelligence\DTOs;

/**
 * Context DTO containing verified historical time-series for cashflow forecasting.
 */
final class CashFlowForecastContextDTO
{
    /**
     * @param int $userId Tenant ID
     * @param int|null $outletId Specific outlet ID (or null for all authorized outlets)
     * @param string $anchorDate Baseline end date (Y-m-d)
     * @param string $timezone Local timezone of primary outlet
     * @param int $horizonDays Forecasting horizon in days (typically 7, max 14)
     * @param int $totalHistoryDays Total calendar days since tenant's first recorded transaction
     * @param array<CashFlowTimeSeriesPointDTO> $historySeries Historical daily series points
     * @param array<string, mixed> $metadata Additional context metadata
     */
    public function __construct(
        public readonly int $userId,
        public readonly ?int $outletId,
        public readonly string $anchorDate,
        public readonly string $timezone,
        public readonly int $horizonDays,
        public readonly int $totalHistoryDays,
        public readonly array $historySeries,
        public readonly array $metadata = []
    ) {
    }

    public static function fromArray(array $data): self
    {
        $series = [];
        foreach ($data['history_series'] ?? [] as $item) {
            if ($item instanceof CashFlowTimeSeriesPointDTO) {
                $series[] = $item;
            } elseif (is_array($item)) {
                $series[] = CashFlowTimeSeriesPointDTO::fromArray($item);
            }
        }

        return new self(
            userId: (int) ($data['user_id'] ?? 0),
            outletId: isset($data['outlet_id']) ? (int) $data['outlet_id'] : null,
            anchorDate: (string) ($data['anchor_date'] ?? ''),
            timezone: (string) ($data['timezone'] ?? 'Asia/Jakarta'),
            horizonDays: (int) ($data['horizon_days'] ?? 7),
            totalHistoryDays: (int) ($data['total_history_days'] ?? 0),
            historySeries: $series,
            metadata: (array) ($data['metadata'] ?? [])
        );
    }
}
