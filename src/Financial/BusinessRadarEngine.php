<?php

declare(strict_types=1);

namespace Kaching\Intelligence\Financial;

use Kaching\Intelligence\Contracts\BusinessRadarEngineInterface;
use Kaching\Intelligence\Contracts\CommodityPriceProviderInterface;
use Kaching\Intelligence\Contracts\WeatherProviderInterface;
use Kaching\Intelligence\DTOs\BusinessRadarContextDTO;
use Kaching\Intelligence\DTOs\BusinessRadarResultDTO;

/**
 * Pure External Business Radar Engine.
 * 
 * Rules:
 * 1. Consumes external data via Phase 6C provider interfaces (Bapanas, Open-Meteo).
 * 2. Produces verified evidence-attributed radar signals.
 * 3. Never hallucinates competitor prices or unverified correlations.
 * 4. Transparently surfaces data freshness, limitations, and source attribution.
 */
final class BusinessRadarEngine implements BusinessRadarEngineInterface
{
    public function __construct(
        private readonly ?CommodityPriceProviderInterface $commodityProvider = null,
        private readonly ?WeatherProviderInterface $weatherProvider = null
    ) {
    }

    public function inspect(BusinessRadarContextDTO $context): BusinessRadarResultDTO
    {
        $category = strtolower($context->category);
        $signals = [];
        $recommendations = [];
        $limitations = [];

        // 1. Raw Material / Commodity Signals
        if (in_array($category, ['all', 'raw_material', 'commodity', 'bahan_baku'], true)) {
            if ($this->commodityProvider !== null) {
                $regionCode = (string) ($context->options['region_code'] ?? '32');
                $commodityResult = $this->commodityProvider->fetchPrices([
                    'province' => $regionCode,
                ]);

                if ($commodityResult->success) {
                    foreach ($commodityResult->items as $item) {
                        $severity = 'LOW';

                        $signals[] = [
                            'title' => "Harga Pasar: {$item->commodityName}",
                            'source' => $commodityResult->sourceAttribution,
                            'published_at' => $item->observationDate,
                            'snippet' => "Rata-rata harga komoditas Rp " . number_format($item->price, 0, ',', '.') . "/{$item->rawUom}.",
                            'category' => 'raw_material',
                            'impact_direction' => 'STABIL',
                            'severity' => $severity,
                            'evidence_attribution' => "Bapanas Price Feed | Wilayah {$regionCode} | {$item->observationDate}",
                        ];
                    }

                    if (!empty($signals)) {
                        $recommendations[] = 'Pantau harga beli faktur dari pemasok lokal untuk komoditas yang mengalami lonjakan di pasar.';
                    }
                } else {
                    $limitations[] = "Sinyal harga komoditas eksternal tidak tersedia: {$commodityResult->errorMessage}";
                }
            } else {
                $limitations[] = 'Provider komoditas pangan eksternal belum dikonfigurasi.';
            }
        }

        // 2. Weather & Local Meteorological Signals
        if (in_array($category, ['all', 'weather', 'cuaca', 'local_event'], true)) {
            if ($this->weatherProvider !== null) {
                $lat = (float) ($context->options['latitude'] ?? -6.200000);
                $lon = (float) ($context->options['longitude'] ?? 106.816666);

                $weatherResult = $this->weatherProvider->fetchWeather($lat, $lon);

                if ($weatherResult->success && $weatherResult->current !== null) {
                    $weather = $weatherResult->current;
                    $isRain = $weather->precipitationMm > 0.0 || in_array($weather->weatherCode, [51, 53, 55, 61, 63, 65, 80, 81, 82], true);
                    $severity = $isRain ? 'MEDIUM' : 'LOW';

                    $signals[] = [
                        'title' => "Kondisi Cuaca Sekitar: {$weather->weatherCondition}",
                        'source' => $weatherResult->sourceAttribution,
                        'published_at' => $weather->timestamp,
                        'snippet' => "Suhu {$weather->temperatureC}°C, kondisi {$weather->weatherCondition}. " . ($isRain ? 'Hujan berpotensi mempengaruhi kunjungan dine-in.' : 'Cuaca cerah mendukung operasional normal.'),
                        'category' => 'weather',
                        'impact_direction' => $isRain ? 'POTENSI_PENURUNAN_DINE_IN' : 'NORMAL',
                        'severity' => $severity,
                        'evidence_attribution' => "Open-Meteo Feed ({$lat}, {$lon}) | {$weather->timestamp}",
                    ];

                    if ($isRain) {
                        $recommendations[] = 'Siapkan armada delivery dan tawarkan menu hangat / minuman untuk menopang penjualan saat hujan.';
                    }
                } else {
                    $limitations[] = "Sinyal prakiraan cuaca tidak dapat diambil: {$weatherResult->errorMessage}";
                }
            } else {
                $limitations[] = 'Provider cuaca eksternal belum dikonfigurasi.';
            }
        }

        $status = !empty($signals) ? 'SUCCESS' : (!empty($limitations) ? 'LIMITATION' : 'NO_DATA');

        return new BusinessRadarResultDTO(
            status: $status,
            category: $category,
            signals: $signals,
            recommendations: $recommendations,
            limitations: $limitations,
            metadata: [
                'evaluated_at' => date('Y-m-d H:i:s'),
                'signals_count' => count($signals),
            ]
        );
    }
}
