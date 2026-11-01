<?php

declare(strict_types=1);

namespace Kaching\Intelligence\DTOs;

/**
 * Context DTO representing a single menu item aggregate across an evaluation window.
 */
final class MenuItemAggregateDTO
{
    /**
     * @param int $menuId Master menu identifier
     * @param string $name Menu name
     * @param string $category Menu category
     * @param float $currentSellingPrice
     * @param float $costPrice Master HPP (cost price)
     * @param float $totalQty Total sold quantity
     * @param int $popularity Order appearance count
     */
    public function __construct(
        public readonly int $menuId,
        public readonly string $name,
        public readonly string $category,
        public readonly float $currentSellingPrice,
        public readonly float $costPrice,
        public readonly float $totalQty,
        public readonly int $popularity
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            menuId: (int) ($data['menu_id'] ?? 0),
            name: (string) ($data['name'] ?? ''),
            category: (string) ($data['category'] ?? 'General'),
            currentSellingPrice: (float) ($data['current_selling_price'] ?? 0.0),
            costPrice: (float) ($data['cost_price'] ?? 0.0),
            totalQty: (float) ($data['total_qty'] ?? 0.0),
            popularity: (int) ($data['popularity'] ?? 0)
        );
    }
}
