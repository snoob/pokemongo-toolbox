<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Port;

use App\Domain\Inventory\Model\Account;
use App\Domain\Inventory\Model\Inventory;

/**
 * Where each account's inventory lives between two runs. Loading what was never saved
 * gives an empty inventory, not an error.
 */
interface InventoryRepository
{
    public function load(Account $account): Inventory;

    public function save(Account $account, Inventory $inventory): void;
}
