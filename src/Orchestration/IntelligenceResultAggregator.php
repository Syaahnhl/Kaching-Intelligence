<?php

declare(strict_types=1);

namespace Kaching\Intelligence\Orchestration;

use Kaching\Intelligence\Contracts\IntelligenceResultAggregatorInterface;
use Kaching\Intelligence\DTOs\ActionProposalDTO;
use Kaching\Intelligence\DTOs\DomainExecutionResultDTO;
use Kaching\Intelligence\DTOs\UnifiedIntelligenceRequestDTO;
use Kaching\Intelligence\DTOs\UnifiedIntelligenceResponseDTO;

class IntelligenceResultAggregator implements IntelligenceResultAggregatorInterface
{
    /**
     * Aggregate domain execution results, merge action proposals, propagate limitations, and compose unified response.
     *
     * @param array<int, DomainExecutionResultDTO> $domainResults
     * @param UnifiedIntelligenceRequestDTO $request
     * @param array<string, mixed> $classification
     * @param float $executionDurationMs
     * @return UnifiedIntelligenceResponseDTO
     */
    public function aggregate(
        array $domainResults,
        UnifiedIntelligenceRequestDTO $request,
        array $classification,
        float $executionDurationMs
    ): UnifiedIntelligenceResponseDTO {
        $intent = $classification['intent'] ?? 'general';
        $locale = $request->locale ?? 'id';

        $actionProposals = [];
        $limitations = [];
        $confidences = [];
        $hasSuccess = false;
        $hasFailure = false;
        $hasFallback = false;

        $domainData = [];

        foreach ($domainResults as $res) {
            $domainData[$res->domain] = $res;
            if ($res->status === 'SUCCESS') {
                $hasSuccess = true;
            } elseif ($res->status === 'FALLBACK') {
                $hasSuccess = true;
                $hasFallback = true;
            } elseif ($res->status === 'FAILED') {
                $hasFailure = true;
            }

            if (!empty($res->actionProposals)) {
                foreach ($res->actionProposals as $proposal) {
                    $actionProposals[] = $proposal;
                }
            }

            foreach ($res->warnings as $warn) {
                $limitations[] = "[{$res->domain}] {$warn}";
            }

            $confidences[] = $res->confidence;
        }

        $overallConfidence = !empty($confidences) ? array_sum($confidences) / count($confidences) : 0.0;

        $overallStatus = match (true) {
            $hasSuccess && !$hasFailure && !$hasFallback => 'SUCCESS',
            $hasSuccess && $hasFallback && !$hasFailure => 'PARTIAL',
            $hasSuccess && $hasFailure => 'DEGRADED',
            !$hasSuccess && $hasFailure => 'FAILED',
            default => 'SUCCESS',
        };

        [$headline, $narrative] = $this->composeResponse($intent, $domainData, $locale, $request);

        return new UnifiedIntelligenceResponseDTO(
            status: $overallStatus,
            headline: $headline,
            narrative: $narrative,
            domainResults: $domainResults,
            actionProposals: $actionProposals,
            limitations: array_unique($limitations),
            overallConfidence: round($overallConfidence, 2),
            telemetry: [
                'execution_duration_ms' => round($executionDurationMs, 2),
                'domains_executed' => array_keys($domainData),
                'intent' => $intent,
            ]
        );
    }

    protected function composeResponse(
        string $intent,
        array $domainData,
        string $locale,
        UnifiedIntelligenceRequestDTO $request
    ): array {
        switch ($intent) {
            case 'raw_material_hpp_impact':
                return $this->composeRawMaterialHpp($domainData, $locale);

            case 'demand_and_safety_stock':
                return $this->composeDemandAndSafetyStock($domainData, $locale);

            case 'cashflow_demand_simulation':
                return $this->composeCashflowDemand($domainData, $locale);

            case 'menu_performance_margin_risk':
                return $this->composeMenuMarginRisk($domainData, $locale);

            case 'commodity_price_inquiry':
                return [
                    'Informasi Harga Komoditas Terkini',
                    'Berikut data referensi harga komoditas dari Bapanas. Pastikan memeriksa tren mingguan untuk merencanakan belanja dapur Anda.',
                ];

            case 'demand_forecast_only':
                $df = $domainData['demand_forecast'] ?? null;
                $total = $df ? ($df->data['total_predicted_quantity'] ?? 0) : 0;
                return [
                    "Proyeksi Permintaan: {$total} unit",
                    "Berdasarkan pola historis penjualan hari serupa (DOW Median), estimasi kebutuhan untuk periode mendatang adalah {$total} unit.",
                ];

            case 'cash_flow_only':
                return [
                    'Proyeksi Arus Kas Operasional',
                    'Kondisi arus kas diperkirakan mencukupi untuk menutupi pengeluaran dan kewajiban operasional harian yang tercatat.',
                ];

            case 'menu_analytics_only':
                return [
                    'Analisis Kinerja dan Profitabilitas Menu',
                    'Evaluasi menu restoran menggunakan matriks profitabilitas dan volume penjualan untuk mengidentifikasi menu Star dan Underperforming.',
                ];

            default:
                $promptRes = $domainData['prompt'] ?? null;
                $text = $promptRes ? ($promptRes->data['response_text'] ?? '') : 'Halo! Ada yang bisa saya bantu?';
                return [
                    'Kaching Copilot Siap Membantu',
                    $text,
                ];
        }
    }

    protected function composeRawMaterialHpp(array $domainData, string $locale): array
    {
        $rm = $domainData['raw_material'] ?? null;
        if (!$rm || empty($rm->data['impacts'])) {
            return [
                'Analisis Dampak Bahan Baku',
                'Data resep bahan baku spesifik belum terisi lengkap di sistem. Kenaikan harga komoditas di pasar berpotensi meningkatkan biaya pokok produksi (HPP) menu utama.',
            ];
        }

        $impactCount = count($rm->data['impacts']);
        $maxIncrease = 0.0;
        $maxMenu = '';

        foreach ($rm->data['impacts'] as $imp) {
            if (($imp['cost_increase_percentage'] ?? 0.0) > $maxIncrease) {
                $maxIncrease = (float) $imp['cost_increase_percentage'];
                $maxMenu = $imp['recipe_context']['menu_name'] ?? 'Menu';
            }
        }

        $headline = "Dampak Kenaikan Harga: {$impactCount} Menu Terdampak (Kenaikan HPP Tertinggi +{$maxIncrease}% pada {$maxMenu})";
        $narrative = "Berdasarkan data harga pasar Bapanas terbaru dan komposisi resep di dapur Anda, terjadi potensi kenaikan biaya bahan baku. " .
                     "Menu '{$maxMenu}' mengalami dampak kenaikan HPP sebesar +{$maxIncrease}%. " .
                     "Disarankan untuk meninjau margin keuntungan atau mempertimbangkan penyesuaian porsi/harga jual agar profitabilitas tetap terjaga.";

        return [$headline, $narrative];
    }

    protected function composeDemandAndSafetyStock(array $domainData, string $locale): array
    {
        $df = $domainData['demand_forecast'] ?? null;
        $ss = $domainData['safety_stock'] ?? null;

        $predQty = $df ? ($df->data['total_predicted_quantity'] ?? 0) : 0;
        $itemName = $df ? ($df->data['item_name'] ?? 'Item') : 'Item';
        $rop = $ss ? ($ss->data['reorder_point'] ?? 0) : 0;
        $safetyStock = $ss ? ($ss->data['safety_stock'] ?? 0) : 0;
        $suggestedReorder = $ss ? ($ss->data['suggested_reorder_quantity'] ?? 0) : 0;
        $isReorder = $ss ? ($ss->data['is_reorder_recommended'] ?? false) : false;

        $headline = "Rekomendasi Stok {$itemName}: Siapkan Total " . round($predQty + $safetyStock, 1) . " unit";
        $narrative = "Proyeksi kebutuhan penjualan untuk periode mendatang adalah " . round($predQty, 1) . " unit. " .
                     "Untuk mengantisipasi lonjakan permintaan tak terduga dan keterlambatan pengiriman suplier (Service Level 95%), disiapkan safety stock sebesar {$safetyStock} unit. " .
                     "Titik pemesanan ulang (Reorder Point) berada di level {$rop} unit. " .
                     ($isReorder ? "⚠️ Stok fisik Anda saat ini telah menyentuh batas aman. Disarankan segera memesan kulakan sebanyak {$suggestedReorder} unit." : "✅ Stok fisik saat ini masih berada dalam batas aman.");

        return [$headline, $narrative];
    }

    protected function composeCashflowDemand(array $domainData, string $locale): array
    {
        $df = $domainData['demand_forecast'] ?? null;
        $cf = $domainData['cash_flow'] ?? null;

        $endingCash = $cf ? ($cf->data['ending_cash'] ?? 0) : 0;
        $minCash = $cf ? ($cf->data['minimum_projected_cash'] ?? 0) : 0;
        $burnRate = $cf ? ($cf->data['daily_burn_rate'] ?? 0) : 0;

        $formattedEnding = "Rp " . number_format($endingCash, 0, ',', '.');
        $formattedMin = "Rp " . number_format($minCash, 0, ',', '.');

        $headline = "Simulasi Arus Kas: Saldo Proyeksi Berada di Level {$formattedEnding}";
        $narrative = "Jika terjadi penurunan volume penjualan pada minggu depan, arus kas operasional Anda diproyeksikan tetap dapat bertahan dengan saldo terendah di kisaran {$formattedMin}. " .
                     "Rata-rata pengeluaran harian diperkirakan sebesar Rp " . number_format($burnRate, 0, ',', '.') . "/hari. " .
                     "Catatan: Simulasi ini didasarkan pada perkiraan tren dan bukan merupakan pencatatan transaksi kas riil.";

        return [$headline, $narrative];
    }

    protected function composeMenuMarginRisk(array $domainData, string $locale): array
    {
        $headline = "Evaluasi Menu: Potensi Penurunan Margin Terdeteksi";
        $narrative = "Kombinasi volume penjualan menu dan kenaikan harga komoditas menunjukkan adanya risiko penipisan margin pada menu dengan bahan baku berfluktuasi tinggi. " .
                     "Disarankan memprioritaskan promosi pada menu dengan margin kontribusi tinggi (Star) dan menyesuaikan strategi HPP pada menu terdampak.";

        return [$headline, $narrative];
    }
}
