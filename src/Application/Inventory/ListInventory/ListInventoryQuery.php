<?php

declare(strict_types=1);

namespace App\Application\Inventory\ListInventory;

use App\Domain\Inventory\Model\Account;
use App\Domain\Pvp\Model\League;

final readonly class ListInventoryQuery
{
    /**
     * @param ?League $league null lists every league
     */
    public function __construct(
        public ?League $league = null,
        public Account $account = new Account(Account::DEFAULT),
    ) {}
}
