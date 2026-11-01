<?php

namespace Kaching\Intelligence\DTOs;

use InvalidArgumentException;

final class ForecastHorizonDTO
{
    /**
     * @param string $startDate YYYY-MM-DD
     * @param string $endDate YYYY-MM-DD
     * @param int $horizonDays Number of days (e.g. 1 to 30)
     */
    public function __construct(
        public readonly string $startDate,
        public readonly string $endDate,
        public readonly int $horizonDays
    ) {
        if ($this->horizonDays <= 0) {
            throw new InvalidArgumentException("Forecast horizon must be at least 1 day, {$this->horizonDays} given.");
        }
    }

    public function toArray(): array
    {
        return [
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'horizon_days' => $this->horizonDays,
        ];
    }
}
