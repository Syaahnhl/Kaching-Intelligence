<?php

namespace Kaching\Intelligence\DTOs;

readonly class FinancialMetricsDTO
{
    public function __construct(
        public int $userId,
        public ?int $outletId,
        public float $todaySales,
        public int $transactionCount,
        public array $paymentMethodBreakdown,
        public array $warnings,
        public array $scenarios,
        public ?array $cashFlowForecast
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            userId: (int) ($data['user_id'] ?? 0),
            outletId: isset($data['outlet_id']) ? (int) $data['outlet_id'] : null,
            todaySales: (float) ($data['today_sales'] ?? 0.0),
            transactionCount: (int) ($data['transaction_count'] ?? 0),
            paymentMethodBreakdown: (array) ($data['payment_methods'] ?? []),
            warnings: (array) ($data['warnings'] ?? []),
            scenarios: (array) ($data['scenarios'] ?? []),
            cashFlowForecast: isset($data['cash_flow']) ? (array) $data['cash_flow'] : null
        );
    }

    public function toArray(): array
    {
        return [
            'user_id'           => $this->userId,
            'outlet_id'         => $this->outletId,
            'today_sales'       => $this->todaySales,
            'transaction_count' => $this->transactionCount,
            'payment_methods'   => $this->paymentMethodBreakdown,
            'warnings'          => $this->warnings,
            'scenarios'         => $this->scenarios,
            'cash_flow'         => $this->cashFlowForecast,
        ];
    }
}
