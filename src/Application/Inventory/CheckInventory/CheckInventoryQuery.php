<?php

declare(strict_types=1);

namespace App\Application\Inventory\CheckInventory;

use App\Domain\Inventory\Model\Account;
use App\Domain\Pokemon\Model\IvFloor;
use App\Domain\Pvp\Model\League;

final readonly class CheckInventoryQuery
{
    /**
     * @param list<League> $leagues empty checks every league
     * @param IvFloor $floor the lowest IV the trade in view can roll
     */
    public function __construct(
        public IvFloor $floor,
        public array $leagues = [],
        public Account $account = new Account(Account::DEFAULT),
    ) {}
}
