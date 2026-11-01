<?php

namespace Kaching\Intelligence\DTOs;

final class ForecastPointDTO
{
    /**
     * @param string $date Date in YYYY-MM-DD
     * @param int $dayOfWeek 1 (Monday) to 7 (Sunday)
     * @param float $predictedQuantity Expected demand quantity
     * @param float|null $lowerBoundP10 10th percentile lower prediction interval
     * @param float|null $upperBoundP90 90th percentile upper prediction interval
     * @param float|null $rawModelOutput Unclipped model regression output
     * @param bool $isClipped Whether raw output was clipped to 0
     * @param array<string, mixed> $pointMetadata Additional point-specific metadata
     */
    public function __construct(
        public readonly string $date,
        public readonly int $dayOfWeek,
        public readonly float $predictedQuantity,
        public readonly ?float $lowerBoundP10 = null,
        public readonly ?float $upperBoundP90 = null,
        public readonly ?float $rawModelOutput = null,
        public readonly bool $isClipped = false,
        public readonly array $pointMetadata = []
    ) {}

    public function toArray(): array
    {
        return [
            'date' => $this->date,
            'day_of_week' => $this->dayOfWeek,
            'predicted_quantity' => $this->predictedQuantity,
            'lower_bound_p10' => $this->lowerBoundP10,
            'upper_bound_p90' => $this->upperBoundP90,
            'raw_model_output' => $this->rawModelOutput,
            'is_clipped' => $this->isClipped,
            'point_metadata' => $this->pointMetadata,
        ];
    }
}
