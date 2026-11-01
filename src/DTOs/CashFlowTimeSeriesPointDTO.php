<?php

declare(strict_types=1);

namespace Kaching\Intelligence\DTOs;

/**
 * Historical time-series point for cashflow inputs.
 */
final class CashFlowTimeSeriesPointDTO
{
    /**
     * @param string $date Business date Y-m-d
     * @param float $inflow Realized cash inflow (Phase A SSOT actual paid)
     * @param float $outflow Realized operating expense outflow
     * @param int $transactionCount Number of completed transactions
     */
    public function __construct(
        public readonly string $date,
        public readonly float $inflow,
        public readonly float $outflow,
        public readonly int $transactionCount = 0
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            date: (string) ($data['date'] ?? ''),
            inflow: (float) ($data['inflow'] ?? 0.0),
            outflow: (float) ($data['outflow'] ?? 0.0),
            transactionCount: (int) ($data['transaction_count'] ?? 0)
        );
    }

    public function toArray(): array
    {
        return [
            'date' => $this->date,
            'inflow' => $this->inflow,
            'outflow' => $this->outflow,
            'transaction_count' => $this->transactionCount,
        ];
    }
}
