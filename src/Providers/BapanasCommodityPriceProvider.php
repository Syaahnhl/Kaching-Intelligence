<?php

declare(strict_types=1);

namespace Kaching\Intelligence\Providers;

use Kaching\Intelligence\Contracts\CommodityPriceProviderInterface;
use Kaching\Intelligence\DTOs\CommodityItemDTO;
use Kaching\Intelligence\DTOs\CommodityResultDTO;

class BapanasCommodityPriceProvider implements CommodityPriceProviderInterface
{
    /**
     * @var callable|null Test / mock HTTP runner: fn(string $url, array $headers, array $params, int $timeout): array{status: int, body: string, headers: array}
     */
    protected static $mockTransport = null;

    public const STRATEGIC_COMMODITIES = [
        'BERAS_PREMIUM' => ['name' => 'Beras Premium', 'category' => 'BAHAN_POKOK', 'raw_uom' => 'kg', 'std_uom' => 'kg', 'multiplier' => 1.0],
        'BERAS_MEDIUM' => ['name' => 'Beras Medium', 'category' => 'BAHAN_POKOK', 'raw_uom' => 'kg', 'std_uom' => 'kg', 'multiplier' => 1.0],
        'CABAI_RAWIT_MERAH' => ['name' => 'Cabai Rawit Merah', 'category' => 'BUMBU', 'raw_uom' => 'kg', 'std_uom' => 'kg', 'multiplier' => 1.0],
        'CABAI_MERAH_KERITING' => ['name' => 'Cabai Merah Keriting', 'category' => 'BUMBU', 'raw_uom' => 'kg', 'std_uom' => 'kg', 'multiplier' => 1.0],
        'BAWANG_MERAH' => ['name' => 'Bawang Merah', 'category' => 'BUMBU', 'raw_uom' => 'kg', 'std_uom' => 'kg', 'multiplier' => 1.0],
        'BAWANG_PUTIH_BONGGOL' => ['name' => 'Bawang Putih Bonggol', 'category' => 'BUMBU', 'raw_uom' => 'kg', 'std_uom' => 'kg', 'multiplier' => 1.0],
        'DAGING_AYAM_RAS' => ['name' => 'Daging Ayam Ras', 'category' => 'PROTEIN', 'raw_uom' => 'kg', 'std_uom' => 'kg', 'multiplier' => 1.0],
        'TELUR_AYAM_RAS' => ['name' => 'Telur Ayam Ras', 'category' => 'PROTEIN', 'raw_uom' => 'kg', 'std_uom' => 'kg', 'multiplier' => 1.0],
        'DAGING_SAPI_MURNI' => ['name' => 'Daging Sapi Murni', 'category' => 'PROTEIN', 'raw_uom' => 'kg', 'std_uom' => 'kg', 'multiplier' => 1.0],
        'MINYAK_GORENG_CURAH' => ['name' => 'Minyak Goreng Curah', 'category' => 'MINYAK_GULA', 'raw_uom' => 'liter', 'std_uom' => 'liter', 'multiplier' => 1.0],
        'MINYAK_GORENG_KEMASAN' => ['name' => 'Minyak Goreng Kemasan', 'category' => 'MINYAK_GULA', 'raw_uom' => 'liter', 'std_uom' => 'liter', 'multiplier' => 1.0],
        'GULA_KONSUMSI' => ['name' => 'Gula Pasir Konsumsi', 'category' => 'MINYAK_GULA', 'raw_uom' => 'kg', 'std_uom' => 'kg', 'multiplier' => 1.0],
        'TEPUNG_TERIGU_CURAH' => ['name' => 'Tepung Terigu Curah', 'category' => 'BAHAN_POKOK', 'raw_uom' => 'kg', 'std_uom' => 'kg', 'multiplier' => 1.0],
    ];

    public function __construct(
        protected readonly ?string $apiKey = null,
        protected readonly string $endpointUrl = 'https://panelharga.badanpangan.go.id/api/harga-pangan-harian',
        protected readonly int $timeoutSeconds = 5
    ) {
    }

    public static function setMockTransport(?callable $transport): void
    {
        self::$mockTransport = $transport;
    }

    public function getIdentifier(): string
    {
        return 'BAPANAS';
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey) && !empty(trim($this->endpointUrl));
    }

    public function fetchPrices(array $options = []): CommodityResultDTO
    {
        $timestamp = gmdate('Y-m-d\TH:i:s\Z');
        $attribution = 'Badan Pangan Nasional Republik Indonesia (Bapanas)';

        if (!$this->isConfigured()) {
            return new CommodityResultDTO(
                success: false,
                status: CommodityResultDTO::STATUS_UNCONFIGURED,
                providerIdentifier: $this->getIdentifier(),
                sourceAttribution: $attribution,
                items: [],
                errorMessage: 'Bapanas API key is not configured or endpoint is missing.',
                httpStatus: null,
                retrievalTimestamp: $timestamp,
                isStale: false,
                metadata: ['configured' => false]
            );
        }

        $referenceDate = $options['reference_date'] ?? date('Y-m-d');
        $regencyCity = strtoupper($options['regency_city'] ?? 'NASIONAL');
        $province = strtoupper($options['province'] ?? 'NASIONAL');
        $priceLevel = strtoupper($options['price_level'] ?? 'KONSUMEN');

        $queryParams = [
            'tanggal' => $referenceDate,
            'tingkat_harga' => $priceLevel,
            'kabupaten' => $regencyCity,
        ];

        $headers = [
            'Accept' => 'application/json',
            'x-api-key' => $this->apiKey,
        ];

        $url = $this->endpointUrl . '?' . http_build_query($queryParams);

        try {
            $response = $this->executeHttp($url, $headers, $this->timeoutSeconds);
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            $isTimeout = str_contains(strtolower($msg), 'timeout') || str_contains(strtolower($msg), 'timed out');

            return new CommodityResultDTO(
                success: false,
                status: $isTimeout ? CommodityResultDTO::STATUS_TIMEOUT : CommodityResultDTO::STATUS_UNAVAILABLE,
                providerIdentifier: $this->getIdentifier(),
                sourceAttribution: $attribution,
                items: [],
                errorMessage: $isTimeout ? 'Request timed out contacting Bapanas API.' : 'Failed to connect to Bapanas API: ' . $msg,
                httpStatus: null,
                retrievalTimestamp: $timestamp,
                isStale: false,
                metadata: ['exception' => get_class($e)]
            );
        }

        $statusCode = $response['status'];
        if ($statusCode === 401 || $statusCode === 403) {
            return new CommodityResultDTO(
                success: false,
                status: CommodityResultDTO::STATUS_UNAVAILABLE,
                providerIdentifier: $this->getIdentifier(),
                sourceAttribution: $attribution,
                items: [],
                errorMessage: 'Unauthorized access to Bapanas API (HTTP ' . $statusCode . ').',
                httpStatus: $statusCode,
                retrievalTimestamp: $timestamp,
                isStale: false
            );
        }

        if ($statusCode === 429) {
            return new CommodityResultDTO(
                success: false,
                status: CommodityResultDTO::STATUS_RATE_LIMIT,
                providerIdentifier: $this->getIdentifier(),
                sourceAttribution: $attribution,
                items: [],
                errorMessage: 'Bapanas API rate limit exceeded.',
                httpStatus: $statusCode,
                retrievalTimestamp: $timestamp,
                isStale: false
            );
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            return new CommodityResultDTO(
                success: false,
                status: CommodityResultDTO::STATUS_UNAVAILABLE,
                providerIdentifier: $this->getIdentifier(),
                sourceAttribution: $attribution,
                items: [],
                errorMessage: 'Bapanas API returned HTTP ' . $statusCode,
                httpStatus: $statusCode,
                retrievalTimestamp: $timestamp,
                isStale: false
            );
        }

        $decoded = json_decode($response['body'], true);
        if (!is_array($decoded)) {
            return new CommodityResultDTO(
                success: false,
                status: CommodityResultDTO::STATUS_INVALID_RESPONSE,
                providerIdentifier: $this->getIdentifier(),
                sourceAttribution: $attribution,
                items: [],
                errorMessage: 'Malformed JSON payload received from Bapanas API.',
                httpStatus: $statusCode,
                retrievalTimestamp: $timestamp,
                isStale: false
            );
        }

        $rawItems = $decoded['data'] ?? $decoded['items'] ?? [];
        if (!is_array($rawItems) || empty($rawItems)) {
            return new CommodityResultDTO(
                success: true,
                status: CommodityResultDTO::STATUS_EMPTY,
                providerIdentifier: $this->getIdentifier(),
                sourceAttribution: $attribution,
                items: [],
                errorMessage: null,
                httpStatus: $statusCode,
                retrievalTimestamp: $timestamp,
                isStale: false,
                metadata: ['total_items' => 0]
            );
        }

        $items = [];
        foreach ($rawItems as $raw) {
            if (!is_array($raw)) {
                continue;
            }
            $code = strtoupper(trim((string) ($raw['commodity_code'] ?? $raw['kode'] ?? '')));
            $name = trim((string) ($raw['commodity_name'] ?? $raw['nama'] ?? ''));
            $price = (float) ($raw['price'] ?? $raw['harga'] ?? 0.0);

            if ($code === '' || $price <= 0.0) {
                continue;
            }

            $meta = self::STRATEGIC_COMMODITIES[$code] ?? [
                'name' => $name !== '' ? $name : $code,
                'category' => 'LAINNYA',
                'raw_uom' => (string) ($raw['uom'] ?? 'kg'),
                'std_uom' => 'kg',
                'multiplier' => 1.0,
            ];

            $multiplier = (float) ($meta['multiplier'] ?? 1.0);
            $stdPrice = $price * $multiplier;

            $items[] = new CommodityItemDTO(
                commodityCode: $code,
                commodityName: (string) $meta['name'],
                price: $price,
                currency: 'IDR',
                rawUom: (string) ($meta['raw_uom'] ?? 'kg'),
                standardUom: (string) ($meta['std_uom'] ?? 'kg'),
                standardPricePerUom: $stdPrice,
                category: (string) ($meta['category'] ?? 'LAINNYA'),
                marketRegion: $regencyCity !== 'NASIONAL' ? $regencyCity : $province,
                priceLevel: $priceLevel,
                observationDate: $referenceDate,
                retrievalTimestamp: $timestamp,
                sourceIdentifier: $this->getIdentifier(),
                sourceAttribution: $attribution,
                dataQuality: 'OFFICIAL_BENCHMARK',
                rawPayload: $raw
            );
        }

        $status = empty($items) ? CommodityResultDTO::STATUS_EMPTY : CommodityResultDTO::STATUS_SUCCESS;

        return new CommodityResultDTO(
            success: true,
            status: $status,
            providerIdentifier: $this->getIdentifier(),
            sourceAttribution: $attribution,
            items: $items,
            errorMessage: null,
            httpStatus: $statusCode,
            retrievalTimestamp: $timestamp,
            isStale: false,
            metadata: ['parsed_count' => count($items), 'raw_count' => count($rawItems)]
        );
    }

    /**
     * Internal HTTP executor supporting pure cURL or static mock transport.
     *
     * @return array{status: int, body: string, headers: array}
     */
    protected function executeHttp(string $url, array $headers, int $timeout): array
    {
        if (self::$mockTransport !== null) {
            return (self::$mockTransport)($url, $headers, [], $timeout);
        }

        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('Failed to initialize cURL handle');
        }

        $flatHeaders = [];
        foreach ($headers as $k => $v) {
            $flatHeaders[] = "$k: $v";
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $flatHeaders,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $body = curl_exec($ch);
        $err = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err !== '') {
            throw new \RuntimeException($err);
        }

        return [
            'status' => $status,
            'body'   => (string) $body,
            'headers' => [],
        ];
    }
}
