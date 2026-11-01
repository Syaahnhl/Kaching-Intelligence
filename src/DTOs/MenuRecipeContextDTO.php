<?php

declare(strict_types=1);

namespace Kaching\Intelligence\DTOs;

final class MenuRecipeContextDTO
{
    /**
     * @param array<int, MenuIngredientContextDTO> $ingredients
     */
    public function __construct(
        public readonly int|string $menuId,
        public readonly string $name,
        public readonly float $sellingPrice,
        public readonly float $recordedCostPrice,
        public readonly array $ingredients = []
    ) {
    }

    public function toArray(): array
    {
        return [
            'menu_id' => $this->menuId,
            'name' => $this->name,
            'selling_price' => $this->sellingPrice,
            'recorded_cost_price' => $this->recordedCostPrice,
            'ingredients' => array_map(fn (MenuIngredientContextDTO $i) => $i->toArray(), $this->ingredients),
        ];
    }
}
