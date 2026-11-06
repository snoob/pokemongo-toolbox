<?php

declare(strict_types=1);

namespace App\Application\Inventory\AddToInventory;

use App\Domain\Inventory\Model\Account;
use App\Domain\Pokemon\Model\IvSpread;
use App\Domain\Pvp\Model\League;

final readonly class AddToInventoryCommand
{
    /**
     * @param non-empty-list<League> $leagues one entry is kept per league
     */
    public function __construct(
        public string $identifier,
        public IvSpread $iv,
        public array $leagues,
        public bool $shadow = false,
        public Account $account = new Account(Account::DEFAULT),
    ) {}
}
