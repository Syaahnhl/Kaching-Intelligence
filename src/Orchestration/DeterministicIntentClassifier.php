<?php

declare(strict_types=1);

namespace Kaching\Intelligence\Orchestration;

use Kaching\Intelligence\Contracts\IntentClassificationInterface;
use Kaching\Intelligence\DTOs\ActorContextDTO;

class DeterministicIntentClassifier implements IntentClassificationInterface
{
    /**
     * @return array{intent: string, domains: array<int, string>, entities: array<string, mixed>, confidence: float}
     */
    public function classify(string $message, ActorContextDTO $actor): array
    {
        $raw = trim($message);
        $lower = strtolower($raw);

        if ($raw === '') {
            return [
                'intent' => 'empty',
                'domains' => [],
                'entities' => [],
                'confidence' => 1.0,
            ];
        }

        $domains = [];
        $entities = [];
        $confidence = 0.95;

        // Entity extraction: Commodity / Raw Material
        if (preg_match('/\b(cabai|cabe|bawang|beras|ayam|minyak|daging|telur|gula|terigu|kopi|susu)\b/i', $lower, $m)) {
            $entities['commodity'] = strtolower($m[1]);
        }

        // Entity extraction: Horizon / Timeframe
        if (preg_match('/\b(minggu depan|next week|7 hari|7 days)\b/i', $lower)) {
            $entities['horizon_days'] = 7;
        } elseif (preg_match('/\b(bulan depan|next month|30 hari|30 days)\b/i', $lower)) {
            $entities['horizon_days'] = 30;
        } elseif (preg_match('/\b(besok|tomorrow|1 hari)\b/i', $lower)) {
            $entities['horizon_days'] = 1;
        }

        // 1. Scenario 1: Raw Material + HPP / Cost impact
        if (
            (preg_match('/\b(cabai|cabe|bawang|beras|ayam|minyak|daging|telur|bahan baku|komoditas)\b/i', $lower) || preg_match('/\b(harga naik|kenaikan harga|harga pasar)\b/i', $lower)) &&
            (preg_match('/\b(hpp|biaya menu|margin|dampaknya|dampak|modal menu)\b/i', $lower))
        ) {
            $domains = ['raw_material'];
            return [
                'intent' => 'raw_material_hpp_impact',
                'domains' => $domains,
                'entities' => $entities,
                'confidence' => 0.95,
            ];
        }

        // 2. Scenario 2: Demand Forecast + Safety Stock
        if (
            preg_match('/\b(stok|persediaan|safety stock|reorder point|rop)\b/i', $lower) &&
            preg_match('/\b(harus saya siapkan|siapkan|butuh berapa|berapa stok|kulakan berapa|forecast|prediksi)\b/i', $lower)
        ) {
            $domains = ['demand_forecast', 'safety_stock'];
            if (!isset($entities['item_name']) && isset($entities['commodity'])) {
                $entities['item_name'] = $entities['commodity'];
            }
            return [
                'intent' => 'demand_and_safety_stock',
                'domains' => $domains,
                'entities' => $entities,
                'confidence' => 0.95,
            ];
        }

        // 3. Scenario 3: Cash Flow + Demand
        if (
            preg_match('/\b(arus kas|cash flow|kas|uang kas)\b/i', $lower) &&
            preg_match('/\b(penjualan turun|omzet turun|demand turun|kalau penjualan|proyeksi kas)\b/i', $lower)
        ) {
            $domains = ['demand_forecast', 'cash_flow'];
            return [
                'intent' => 'cashflow_demand_simulation',
                'domains' => $domains,
                'entities' => $entities,
                'confidence' => 0.95,
            ];
        }

        // 4. Scenario 4: Menu Performance + Margin Risk
        if (
            preg_match('/\b(menu mana|evaluasi menu|menu berisiko|margin turun|margin-nya berisiko)\b/i', $lower)
        ) {
            $domains = ['menu_analytics', 'raw_material'];
            return [
                'intent' => 'menu_performance_margin_risk',
                'domains' => $domains,
                'entities' => $entities,
                'confidence' => 0.95,
            ];
        }

        // 5. Single domain: Raw Material / Commodity
        if (preg_match('/\b(harga cabai|harga bawang|harga komoditas|harga pasar|bapanas)\b/i', $lower)) {
            return [
                'intent' => 'commodity_price_inquiry',
                'domains' => ['raw_material'],
                'entities' => $entities,
                'confidence' => 0.90,
            ];
        }

        // 6. Single domain: Demand Forecast
        if (preg_match('/\b(ramalan penjualan|prediksi penjualan|forecast demand|permintaan minggu depan)\b/i', $lower)) {
            return [
                'intent' => 'demand_forecast_only',
                'domains' => ['demand_forecast'],
                'entities' => $entities,
                'confidence' => 0.90,
            ];
        }

        // 7. Single domain: Cash Flow
        if (preg_match('/\b(proyeksi kas|arus kas 7 hari|cash flow minggu depan)\b/i', $lower)) {
            return [
                'intent' => 'cash_flow_only',
                'domains' => ['cash_flow'],
                'entities' => $entities,
                'confidence' => 0.90,
            ];
        }

        // 8. Single domain: Menu Analytics / Radar
        if (preg_match('/\b(menu terlaris|analisis menu|star menu|dog menu|business radar)\b/i', $lower)) {
            return [
                'intent' => 'menu_analytics_only',
                'domains' => ['menu_analytics'],
                'entities' => $entities,
                'confidence' => 0.90,
            ];
        }

        // Default Fallback: Conversational Prompt
        return [
            'intent' => 'conversational_general',
            'domains' => ['prompt'],
            'entities' => $entities,
            'confidence' => 0.75,
        ];
    }
}
