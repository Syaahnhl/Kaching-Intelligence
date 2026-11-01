<?php

declare(strict_types=1);

namespace Kaching\Intelligence\RawMaterial;

class CommodityClassifier
{
    public const COMMODITY_KEYWORD_MAP = [
        'CABAI_RAWIT_MERAH' => ['cabai rawit', 'cabe rawit', 'rawit merah', 'lombok rawit'],
        'CABAI_MERAH_KERITING' => ['cabai merah', 'cabe merah', 'cabai keriting', 'lombok merah'],
        'BAWANG_MERAH' => ['bawang merah', 'shallot'],
        'BAWANG_PUTIH_BONGGOL' => ['bawang putih', 'garlic'],
        'TELUR_AYAM_RAS' => ['telur ayam', 'telur', 'telor'],
        'DAGING_AYAM_RAS' => ['ayam', 'daging ayam', 'fillet ayam', 'dada ayam', 'paha ayam', 'broiler'],
        'DAGING_SAPI_MURNI' => ['daging sapi', 'sapi', 'beef'],
        'MINYAK_GORENG_CURAH' => ['minyak curah', 'minyak goreng curah', 'minyak'],
        'MINYAK_GORENG_KEMASAN' => ['minyak kemasan', 'bimoli', 'filma', 'sunco', 'tropical'],
        'BERAS_MEDIUM' => ['beras medium', 'beras', 'rice'],
        'BERAS_PREMIUM' => ['beras premium', 'beras pandan wangi', 'beras rojolele'],
        'GULA_KONSUMSI' => ['gula pasir', 'gula putih', 'gula'],
        'TEPUNG_TERIGU_CURAH' => ['tepung terigu', 'terigu', 'tepung gandum'],
    ];

    public const COMMODITY_STANDARD_UOMS = [
        'CABAI_RAWIT_MERAH' => 'kg',
        'CABAI_MERAH_KERITING' => 'kg',
        'BAWANG_MERAH' => 'kg',
        'BAWANG_PUTIH_BONGGOL' => 'kg',
        'TELUR_AYAM_RAS' => 'kg',
        'DAGING_AYAM_RAS' => 'kg',
        'DAGING_SAPI_MURNI' => 'kg',
        'MINYAK_GORENG_CURAH' => 'liter',
        'MINYAK_GORENG_KEMASAN' => 'liter',
        'BERAS_MEDIUM' => 'kg',
        'BERAS_PREMIUM' => 'kg',
        'GULA_KONSUMSI' => 'kg',
        'TEPUNG_TERIGU_CURAH' => 'kg',
    ];

    public const COMMODITY_LABELS = [
        'CABAI_RAWIT_MERAH' => 'Cabai Rawit Merah',
        'CABAI_MERAH_KERITING' => 'Cabai Merah Keriting',
        'BAWANG_MERAH' => 'Bawang Merah',
        'BAWANG_PUTIH_BONGGOL' => 'Bawang Putih Bonggol',
        'DAGING_AYAM_RAS' => 'Daging Ayam Ras',
        'TELUR_AYAM_RAS' => 'Telur Ayam Ras',
        'DAGING_SAPI_MURNI' => 'Daging Sapi Murni',
        'MINYAK_GORENG_CURAH' => 'Minyak Goreng Curah',
        'MINYAK_GORENG_KEMASAN' => 'Minyak Goreng Kemasan',
        'BERAS_MEDIUM' => 'Beras Medium',
        'BERAS_PREMIUM' => 'Beras Premium',
        'GULA_KONSUMSI' => 'Gula Konsumsi',
        'TEPUNG_TERIGU_CURAH' => 'Tepung Terigu Curah',
    ];

    public static function resolveCommodityCode(string $name, ?string $explicitCode = null): ?string
    {
        if (!empty($explicitCode)) {
            $upper = strtoupper(trim($explicitCode));
            if (isset(self::COMMODITY_LABELS[$upper]) || isset(self::COMMODITY_KEYWORD_MAP[$upper])) {
                return $upper;
            }
        }

        $nameLower = strtolower(trim($name));
        foreach (self::COMMODITY_KEYWORD_MAP as $code => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($nameLower, $kw)) {
                    return $code;
                }
            }
        }

        return null;
    }
}
