<?php

declare(strict_types=1);

namespace Tests\Unit;

use Kaching\Intelligence\DTOs\MenuIngredientContextDTO;
use Kaching\Intelligence\DTOs\MenuRecipeContextDTO;
use Kaching\Intelligence\DTOs\RawMaterialItemContextDTO;
use Kaching\Intelligence\RawMaterial\CommodityClassifier;
use Kaching\Intelligence\RawMaterial\RawMaterialImpactEngine;
use Kaching\Intelligence\RawMaterial\UnitDimensionNormalizer;
use PHPUnit\Framework\TestCase;

class RawMaterialImpactEngineUnitTest extends TestCase
{
    public function test_unit_dimension_normalizer_standard_conversions(): void
    {
        $res = UnitDimensionNormalizer::convert(250, 'g', 'kg');
        $this->assertTrue($res['success']);
        $this->assertEquals(0.25, $res['converted_qty']);

        $res2 = UnitDimensionNormalizer::convert(500, 'ml', 'liter');
        $this->assertTrue($res2['success']);
        $this->assertEquals(0.5, $res2['converted_qty']);

        // Mismatch dimension
        $mismatch = UnitDimensionNormalizer::convert(100, 'g', 'liter');
        $this->assertFalse($mismatch['success']);
        $this->assertSame('UNIT_DIMENSION_MISMATCH', $mismatch['reason']);
    }

    public function test_commodity_classifier_keywords(): void
    {
        $code = CommodityClassifier::resolveCommodityCode('Cabai Rawit Setan Merah');
        $this->assertSame('CABAI_RAWIT_MERAH', $code);

        $codeAyam = CommodityClassifier::resolveCommodityCode('Dada Fillet Ayam Broiler');
        $this->assertSame('DAGING_AYAM_RAS', $codeAyam);

        $codeExplicit = CommodityClassifier::resolveCommodityCode('Bumbu Dapur Khusus', 'BAWANG_MERAH');
        $this->assertSame('BAWANG_MERAH', $codeExplicit);
    }

    public function test_deterministic_hpp_shock_simulation(): void
    {
        // Setup: Cabai rawit cost Rp 40.000 / kg (base uom: kg)
        // Ayam fillet cost Rp 35.000 / kg (base uom: kg)
        $rawMaterials = [
            new RawMaterialItemContextDTO(
                itemId: 1,
                name: 'Cabai Rawit Merah',
                costPrice: 40000.0,
                baseUom: 'kg'
            ),
            new RawMaterialItemContextDTO(
                itemId: 2,
                name: 'Daging Ayam Fillet',
                costPrice: 35000.0,
                baseUom: 'kg'
            ),
        ];

        // Recipe: Ayam Geprek Sambal Korek
        // Uses: 200g ayam (0.2kg * 35000 = 7000) + 20g cabai (0.02kg * 40000 = 800)
        // Baseline HPP = 7800. Selling price = 15000.
        // Shock: +20% on Cabai Rawit Merah -> cabai becomes 48000/kg (0.02kg * 48000 = 960)
        // Simulated HPP = 7000 + 960 = 7960 (Delta +160 Rp).
        $recipe = new MenuRecipeContextDTO(
            menuId: 101,
            name: 'Ayam Geprek Sambal Korek',
            sellingPrice: 15000.0,
            recordedCostPrice: 7800.0,
            ingredients: [
                new MenuIngredientContextDTO(itemId: 2, qty: 200, uom: 'g'),
                new MenuIngredientContextDTO(itemId: 1, qty: 20, uom: 'g'),
            ]
        );

        $engine = new RawMaterialImpactEngine();
        $result = $engine->simulateImpact(
            rawMaterials: $rawMaterials,
            recipes: [$recipe],
            shockPercent: 20.0,
            filterCommodityCode: 'CABAI_RAWIT_MERAH'
        );

        $this->assertTrue($result->success);
        $this->assertSame(1, $result->totalMenusEvaluated);
        $menuResult = $result->menusEvaluated[0];

        $this->assertEquals(7800.0, $menuResult['original_hpp']);
        $this->assertEquals(7960.0, $menuResult['simulated_hpp']);
        $this->assertEquals(160.0, $menuResult['delta_hpp_rp']);
        $this->assertTrue($menuResult['has_shock_applied']);
        $this->assertNotEmpty($result->recommendations);
    }
}
