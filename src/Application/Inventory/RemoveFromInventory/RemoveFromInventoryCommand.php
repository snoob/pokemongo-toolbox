<?php

declare(strict_types=1);

namespace App\Application\Inventory\RemoveFromInventory;

final readonly class RemoveFromInventoryCommand
{
    public function __construct(
        public int $id,
    ) {}
}
