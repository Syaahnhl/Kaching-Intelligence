<?php

declare(strict_types=1);

namespace Kaching\Intelligence\Financial;

use Kaching\Intelligence\Contracts\CashFlowForecastingEngineInterface;
use Kaching\Intelligence\DTOs\CashFlowForecastContextDTO;
use Kaching\Intelligence\DTOs\ForecastResultDTO;

/**
 * Deterministic Cash-Flow Forecasting Engine.
 * 
 * Rules:
 * 1. Pure reasoning and mathematical projection: 0 DB queries, 0 framework dependencies.
 * 2. Model Hierarchy:
 *    - Tier 1: Day-of-Week (DOW) Seasonal Baseline (>=28d history, >=14 active days).
 *              Median of 4 observations per weekday, robust to single outliers.
 *    - Tier 2: Weighted Moving Average (WMA-14) (14-27d history OR 7-13 active days).
 *              System-wide fallback (never mixed per weekday).
 *    - Tier 3: Fail-Closed (INSUFFICIENT_HISTORY or SPARSE_DATA or ZERO_BASELINE).
 * 3. Never mutates historical actuals.
 * 4. Missing calendar days in history default to 0.0 with explicit tracking.
 */
final class CashFlowForecastingEngine implements CashFlowForecastingEngineInterface
{
    public const MODEL_DOW_SEASONAL = 'DAY_OF_WEEK_SEASONAL_BASELINE';
    public const MODEL_WMA14        = 'WEIGHTED_MOVING_AVERAGE_14';
    public const MODEL_NONE         = 'NONE';

    public const DQ_SUFFICIENT           = 'SUFFICIENT';
    public const DQ_MODERATE_HISTORY     = 'MODERATE_HISTORY';
    public const DQ_INSUFFICIENT_HISTORY = 'INSUFFICIENT_HISTORY';
    public const DQ_SPARSE_DATA          = 'SPARSE_DATA';
    public const DQ_ZERO_BASELINE        = 'ZERO_BASELINE';

    public const DEFAULT_HORIZON_DAYS     = 7;
    public const DOW_CALENDAR_WINDOW_DAYS = 28;
    public const DOW_MIN_ACTIVE_DAYS      = 14;
    public const WMA_CALENDAR_WINDOW_DAYS = 14;
    public const WMA_MIN_ACTIVE_DAYS      = 7;

    public const WEEKDAY_NAMES = [
        1 => 'Senin',
        2 => 'Selasa',
        3 => 'Rabu',
        4 => 'Kamis',
        5 => 'Jumat',
        6 => 'Sabtu',
        7 => 'Minggu',
    ];

    public function forecast(CashFlowForecastContextDTO $context): ForecastResultDTO
    {
        $horizonDays = $context->horizonDays;
        if ($horizonDays <= 0 || $horizonDays > 14) {
            $horizonDays = self::DEFAULT_HORIZON_DAYS;
        }

        $totalHistoryDays = $context->totalHistoryDays;
        $anchorDate = $context->anchorDate;

        // Check overall history span
        if ($totalHistoryDays < self::WMA_CALENDAR_WINDOW_DAYS) {
            return $this->buildFailClosed(
                status: self::DQ_INSUFFICIENT_HISTORY,
                dataQualityState: self::DQ_INSUFFICIENT_HISTORY,
                horizonDays: $horizonDays,
                message: "Riwayat transaksi baru tercatat {$totalHistoryDays} hari. Proyeksi membutuhkan minimal " . self::WMA_CALENDAR_WINDOW_DAYS . " hari data.",
                historyDays: $totalHistoryDays
            );
        }

        // Map series points into calendar dictionary: date => point
        $calendarBuckets = [];
        $expenseBuckets = [];
        foreach ($context->historySeries as $point) {
            $calendarBuckets[$point->date] = $point->inflow;
            $expenseBuckets[$point->date] = $point->outflow;
        }

        // Reconstruct baseline calendar window (28 calendar days up to anchorDate)
        $windowDays = self::DOW_CALENDAR_WINDOW_DAYS;
        $windowDates = [];
        $anchorTs = strtotime($anchorDate);
        if ($anchorTs === false) {
            $anchorTs = time();
            $anchorDate = date('Y-m-d', $anchorTs);
        }

        for ($i = $windowDays - 1; $i >= 0; $i--) {
            $windowDates[] = date('Y-m-d', strtotime("-{$i} days", $anchorTs));
        }

        $activeDaysCount = 0;
        $totalBaselineInflow = 0.0;
        $totalHistoricalExpense = 0.0;

        foreach ($windowDates as $dateStr) {
            $inflow = $calendarBuckets[$dateStr] ?? 0.0;
            $outflow = $expenseBuckets[$dateStr] ?? 0.0;

            if ($inflow > 0.0) {
                $activeDaysCount++;
            }
            $totalBaselineInflow += $inflow;
            $totalHistoricalExpense += $outflow;
        }

        if ($activeDaysCount === 0 || $totalBaselineInflow <= 0.0) {
            return $this->buildFailClosed(
                status: self::DQ_ZERO_BASELINE,
                dataQualityState: self::DQ_ZERO_BASELINE,
                horizonDays: $horizonDays,
                message: 'Tidak ada pemasukan tercatat dalam 28 hari terakhir.',
                historyDays: $totalHistoryDays,
                activeDays: $activeDaysCount
            );
        }

        // Model Tier Selection
        $model = self::MODEL_NONE;
        $dataQualityState = self::DQ_SUFFICIENT;

        if ($totalHistoryDays >= self::DOW_CALENDAR_WINDOW_DAYS && $activeDaysCount >= self::DOW_MIN_ACTIVE_DAYS) {
            $model = self::MODEL_DOW_SEASONAL;
            $dataQualityState = self::DQ_SUFFICIENT;
        } elseif ($totalHistoryDays >= self::WMA_CALENDAR_WINDOW_DAYS && $activeDaysCount >= self::WMA_MIN_ACTIVE_DAYS) {
            $model = self::MODEL_WMA14;
            $dataQualityState = self::DQ_MODERATE_HISTORY;
        } else {
            return $this->buildFailClosed(
                status: self::DQ_SPARSE_DATA,
                dataQualityState: self::DQ_SPARSE_DATA,
                horizonDays: $horizonDays,
                message: "Data transaksi terlalu jarang ({$activeDaysCount} hari aktif dari 28 hari). Butuh minimal " . self::WMA_MIN_ACTIVE_DAYS . " hari aktif.",
                historyDays: $totalHistoryDays,
                activeDays: $activeDaysCount
            );
        }

        // Projection vectors
        $dailyForecastInflows = [];
        $regularlyClosedWeekdays = [];

        if ($model === self::MODEL_DOW_SEASONAL) {
            // Group observations by Day of Week (1 = Mon ... 7 = Sun)
            $dowObservations = [1 => [], 2 => [], 3 => [], 4 => [], 5 => [], 6 => [], 7 => []];
            foreach ($windowDates as $dateStr) {
                $dow = (int) date('N', strtotime($dateStr));
                $inflow = $calendarBuckets[$dateStr] ?? 0.0;
                $dowObservations[$dow][] = (float) $inflow;
            }

            // Calculate median per DOW and detect regularly closed days
            $dowBaselines = [];
            foreach ($dowObservations as $dow => $obs) {
                $zeroCount = count(array_filter($obs, fn ($val) => $val <= 0.0));
                if ($zeroCount >= 3) {
                    $regularlyClosedWeekdays[] = self::WEEKDAY_NAMES[$dow];
                    $dowBaselines[$dow] = 0.0;
                    continue;
                }
                $dowBaselines[$dow] = $this->calculateMedian($obs);
            }

            for ($i = 1; $i <= $horizonDays; $i++) {
                $targetTs = strtotime("+{$i} days", $anchorTs);
                $targetDate = date('Y-m-d', $targetTs);
                $targetDow = (int) date('N', $targetTs);
                $dailyForecastInflows[$targetDate] = $dowBaselines[$targetDow] ?? 0.0;
            }
        } else {
            // Tier 2: WMA-14 Fallback
            $wmaDates = [];
            for ($k = self::WMA_CALENDAR_WINDOW_DAYS - 1; $k >= 0; $k--) {
                $wmaDates[] = date('Y-m-d', strtotime("-{$k} days", $anchorTs));
            }

            $weightedSum = 0.0;
            $totalWeight = 0;
            $weightIndex = 1;
            foreach ($wmaDates as $dateStr) {
                $val = (float) ($calendarBuckets[$dateStr] ?? 0.0);
                $weightedSum += ($weightIndex * $val);
                $totalWeight += $weightIndex;
                $weightIndex++;
            }

            $wmaDailyAverage = round($weightedSum / max(1, $totalWeight), 2);

            for ($i = 1; $i <= $horizonDays; $i++) {
                $targetDate = date('Y-m-d', strtotime("+{$i} days", $anchorTs));
                $dailyForecastInflows[$targetDate] = $wmaDailyAverage;
            }
        }

        // Outflow Calculation: average daily run-rate over window
        $dailyExpenseAverage = round($totalHistoricalExpense / (float) $windowDays, 2);
        $projectedTotalOutflow = round($dailyExpenseAverage * $horizonDays, 2);

        // Compile daily vector
        $dailyForecast = [];
        $projectedTotalInflow = 0.0;

        for ($i = 1; $i <= $horizonDays; $i++) {
            $targetTs = strtotime("+{$i} days", $anchorTs);
            $targetDate = date('Y-m-d', $targetTs);
            $targetDow = (int) date('N', $targetTs);
            $dayName = self::WEEKDAY_NAMES[$targetDow];

            $dayInflow = round($dailyForecastInflows[$targetDate] ?? 0.0, 2);
            $dayOutflow = $dailyExpenseAverage;
            $dayNet = round($dayInflow - $dayOutflow, 2);

            $projectedTotalInflow += $dayInflow;

            $dailyForecast[] = [
                'date' => $targetDate,
                'day_of_week' => $dayName,
                'projected_inflow' => $dayInflow,
                'projected_outflow' => $dayOutflow,
                'projected_net_operating_cash_flow' => $dayNet,
            ];
        }

        $projectedTotalInflow = round($projectedTotalInflow, 2);
        $projectedNetOperatingCashFlow = round($projectedTotalInflow - $projectedTotalOutflow, 2);

        $recommendations = [];
        if ($projectedNetOperatingCashFlow < 0) {
            $recommendations[] = 'Proyeksi arus kas operasional menunjukkan defisit negatif. Evaluasi beban operasional harian atau percepat penagihan.';
        } elseif ($model === self::MODEL_WMA14) {
            $recommendations[] = 'Proyeksi menggunakan model WMA 14-hari karena data musiman DOW belum mencukupi. Terus catat transaksi untuk akurasi lebih tinggi.';
        } else {
            $recommendations[] = 'Arus kas operasional diproyeksikan dalam batas aman stabil berdasarkan median musiman harian.';
        }

        if (!empty($regularlyClosedWeekdays)) {
            $recommendations[] = 'Toko terdeteksi tutup berkala pada hari: ' . implode(', ', array_unique($regularlyClosedWeekdays)) . '.';
        }

        return new ForecastResultDTO(
            status: 'SUCCESS',
            model: $model,
            dataQualityState: $dataQualityState,
            horizonDays: $horizonDays,
            projectedTotalInflow: $projectedTotalInflow,
            projectedTotalOutflow: $projectedTotalOutflow,
            projectedNetOperatingCashFlow: $projectedNetOperatingCashFlow,
            dailyForecast: $dailyForecast,
            qualityDetails: [
                'state' => $dataQualityState,
                'total_history_days' => $totalHistoryDays,
                'baseline_active_days' => $activeDaysCount,
                'baseline_total_days' => $windowDays,
                'baseline_total_inflow' => $totalBaselineInflow,
                'baseline_total_expense' => $totalHistoricalExpense,
                'regularly_closed_weekdays' => $regularlyClosedWeekdays,
            ],
            recommendations: $recommendations,
            message: 'Proyeksi arus kas berhasil dihitung secara deterministik.'
        );
    }

    /**
     * Calculate median of a float array.
     *
     * @param array<float> $numbers
     * @return float
     */
    private function calculateMedian(array $numbers): float
    {
        if (empty($numbers)) {
            return 0.0;
        }

        sort($numbers, SORT_NUMERIC);
        $count = count($numbers);
        $middle = (int) floor($count / 2);

        if ($count % 2 === 1) {
            return round((float) $numbers[$middle], 2);
        }

        return round(((float) $numbers[$middle - 1] + (float) $numbers[$middle]) / 2.0, 2);
    }

    private function buildFailClosed(
        string $status,
        string $dataQualityState,
        int $horizonDays,
        string $message,
        int $historyDays = 0,
        int $activeDays = 0
    ): ForecastResultDTO {
        return new ForecastResultDTO(
            status: $status,
            model: self::MODEL_NONE,
            dataQualityState: $dataQualityState,
            horizonDays: $horizonDays,
            projectedTotalInflow: 0.0,
            projectedTotalOutflow: 0.0,
            projectedNetOperatingCashFlow: 0.0,
            dailyForecast: [],
            qualityDetails: [
                'state' => $dataQualityState,
                'total_history_days' => $historyDays,
                'baseline_active_days' => $activeDays,
                'error_detail' => $message,
            ],
            recommendations: [
                'Tingkatkan pencatatan transaksi POS secara konsisten minimal 14-28 hari agar mesin proyeksi dapat aktif.'
            ],
            message: $message
        );
    }
}
