<?php

declare(strict_types=1);

namespace Kaching\Intelligence\RawMaterial;

class UnitDimensionNormalizer
{
    public const DIMENSION_MASS = 'MASS';
    public const DIMENSION_VOLUME = 'VOLUME';
    public const DIMENSION_COUNT = 'COUNT';
    public const DIMENSION_PORTION = 'PORTION';
    public const DIMENSION_UNKNOWN = 'UNKNOWN';

    protected static array $canonicalMap = [
        // Mass (base: g)
        'g' => ['canonical' => 'g', 'dimension' => self::DIMENSION_MASS, 'base_factor' => 1.0],
        'gr' => ['canonical' => 'g', 'dimension' => self::DIMENSION_MASS, 'base_factor' => 1.0],
        'gram' => ['canonical' => 'g', 'dimension' => self::DIMENSION_MASS, 'base_factor' => 1.0],
        'grams' => ['canonical' => 'g', 'dimension' => self::DIMENSION_MASS, 'base_factor' => 1.0],
        'kg' => ['canonical' => 'kg', 'dimension' => self::DIMENSION_MASS, 'base_factor' => 1000.0],
        'kilo' => ['canonical' => 'kg', 'dimension' => self::DIMENSION_MASS, 'base_factor' => 1000.0],
        'kilogram' => ['canonical' => 'kg', 'dimension' => self::DIMENSION_MASS, 'base_factor' => 1000.0],
        'kilograms' => ['canonical' => 'kg', 'dimension' => self::DIMENSION_MASS, 'base_factor' => 1000.0],

        // Volume (base: ml)
        'ml' => ['canonical' => 'ml', 'dimension' => self::DIMENSION_VOLUME, 'base_factor' => 1.0],
        'mili' => ['canonical' => 'ml', 'dimension' => self::DIMENSION_VOLUME, 'base_factor' => 1.0],
        'mililiter' => ['canonical' => 'ml', 'dimension' => self::DIMENSION_VOLUME, 'base_factor' => 1.0],
        'milliliter' => ['canonical' => 'ml', 'dimension' => self::DIMENSION_VOLUME, 'base_factor' => 1.0],
        'l' => ['canonical' => 'l', 'dimension' => self::DIMENSION_VOLUME, 'base_factor' => 1000.0],
        'liter' => ['canonical' => 'l', 'dimension' => self::DIMENSION_VOLUME, 'base_factor' => 1000.0],
        'liters' => ['canonical' => 'l', 'dimension' => self::DIMENSION_VOLUME, 'base_factor' => 1000.0],
        'ltr' => ['canonical' => 'l', 'dimension' => self::DIMENSION_VOLUME, 'base_factor' => 1000.0],

        // Count (base: pcs / butir)
        'pcs' => ['canonical' => 'pcs', 'dimension' => self::DIMENSION_COUNT, 'base_factor' => 1.0],
        'pc' => ['canonical' => 'pcs', 'dimension' => self::DIMENSION_COUNT, 'base_factor' => 1.0],
        'piece' => ['canonical' => 'pcs', 'dimension' => self::DIMENSION_COUNT, 'base_factor' => 1.0],
        'pieces' => ['canonical' => 'pcs', 'dimension' => self::DIMENSION_COUNT, 'base_factor' => 1.0],
        'biji' => ['canonical' => 'pcs', 'dimension' => self::DIMENSION_COUNT, 'base_factor' => 1.0],
        'unit' => ['canonical' => 'pcs', 'dimension' => self::DIMENSION_COUNT, 'base_factor' => 1.0],
        'lembar' => ['canonical' => 'pcs', 'dimension' => self::DIMENSION_COUNT, 'base_factor' => 1.0],
        'butir' => ['canonical' => 'butir', 'dimension' => self::DIMENSION_COUNT, 'base_factor' => 1.0],

        // Portion
        'porsi' => ['canonical' => 'porsi', 'dimension' => self::DIMENSION_PORTION, 'base_factor' => 1.0],
        'portion' => ['canonical' => 'porsi', 'dimension' => self::DIMENSION_PORTION, 'base_factor' => 1.0],
        'serving' => ['canonical' => 'porsi', 'dimension' => self::DIMENSION_PORTION, 'base_factor' => 1.0],
        'cup' => ['canonical' => 'porsi', 'dimension' => self::DIMENSION_PORTION, 'base_factor' => 1.0],
    ];

    public static function normalizeUnit(?string $unit): ?string
    {
        if ($unit === null) {
            return null;
        }

        $clean = trim(mb_strtolower($unit, 'UTF-8'));
        $clean = rtrim($clean, '.');

        if ($clean === '') {
            return null;
        }

        if (isset(self::$canonicalMap[$clean])) {
            return self::$canonicalMap[$clean]['canonical'];
        }

        return $clean;
    }

    public static function getDimension(?string $canonicalUnit): string
    {
        $norm = self::normalizeUnit($canonicalUnit);
        if ($norm === null || !isset(self::$canonicalMap[$norm])) {
            return self::DIMENSION_UNKNOWN;
        }

        return self::$canonicalMap[$norm]['dimension'];
    }

    /**
     * Convert qty from source unit to target unit deterministically.
     *
     * @param float $qty
     * @param string $sourceUnit
     * @param string $targetUnit
     * @param array<string, float> $customConversions
     * @return array{success: bool, converted_qty: ?float, reason: ?string}
     */
    public static function convert(float $qty, string $sourceUnit, string $targetUnit, array $customConversions = []): array
    {
        $srcNorm = self::normalizeUnit($sourceUnit);
        $tgtNorm = self::normalizeUnit($targetUnit);

        if ($srcNorm === null || $tgtNorm === null) {
            return ['success' => false, 'converted_qty' => null, 'reason' => 'UNKNOWN_UNIT'];
        }

        if ($srcNorm === $tgtNorm) {
            return ['success' => true, 'converted_qty' => $qty, 'reason' => null];
        }

        // Check custom packaging conversion first (e.g. 1 ikat -> 0.25 kg)
        if (isset($customConversions[$tgtNorm]) && $customConversions[$tgtNorm] > 0.0) {
            return [
                'success' => true,
                'converted_qty' => round($qty * $customConversions[$tgtNorm], 6),
                'reason' => 'CUSTOM_CONVERSION',
            ];
        }

        $srcDim = self::getDimension($srcNorm);
        $tgtDim = self::getDimension($tgtNorm);

        if ($srcDim === self::DIMENSION_UNKNOWN || $tgtDim === self::DIMENSION_UNKNOWN) {
            return ['success' => false, 'converted_qty' => null, 'reason' => 'UNKNOWN_DIMENSION'];
        }

        if ($srcDim !== $tgtDim) {
            return ['success' => false, 'converted_qty' => null, 'reason' => 'UNIT_DIMENSION_MISMATCH'];
        }

        $srcFactor = self::$canonicalMap[$srcNorm]['base_factor'] ?? 1.0;
        $tgtFactor = self::$canonicalMap[$tgtNorm]['base_factor'] ?? 1.0;

        if ($tgtFactor <= 0.0) {
            return ['success' => false, 'converted_qty' => null, 'reason' => 'INVALID_BASE_FACTOR'];
        }

        // Qty * (srcFactor / tgtFactor)
        // e.g. 250 g to kg: 250 * (1.0 / 1000.0) = 0.25 kg
        $converted = ($qty * $srcFactor) / $tgtFactor;

        return [
            'success' => true,
            'converted_qty' => round($converted, 6),
            'reason' => 'STANDARD_DIMENSIONAL_CONVERSION',
        ];
    }
}
