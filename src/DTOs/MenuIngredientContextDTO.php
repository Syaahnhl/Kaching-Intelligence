<?php

declare(strict_types=1);

namespace Kaching\Intelligence\DTOs;

final class MenuIngredientContextDTO
{
    public function __construct(
        public readonly int|string $itemId,
        public readonly float $qty,
        public readonly ?string $uom = null
    ) {
    }

    public function toArray(): array
    {
        return [
            'item_id' => $this->itemId,
            'qty' => $this->qty,
            'uom' => $this->uom,
        ];
    }
}
