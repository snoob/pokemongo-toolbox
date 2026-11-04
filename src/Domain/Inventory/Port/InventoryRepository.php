<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Port;

use App\Domain\Inventory\Model\Inventory;

/**
 * Where the inventory lives between two runs. Loading what was never saved gives an
 * empty inventory, not an error.
 */
interface InventoryRepository
{
    public function load(): Inventory;

    public function save(Inventory $inventory): void;
}
