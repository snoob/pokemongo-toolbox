<?php

declare(strict_types=1);

namespace App\Application\Inventory\RemoveFromInventory;

use App\Domain\Inventory\Model\Account;

final readonly class RemoveFromInventoryCommand
{
    public function __construct(
        public int $id,
        public Account $account = new Account(Account::DEFAULT),
    ) {}
}
