<?php

declare(strict_types=1);

namespace App\Application\Inventory\AddToInventory;

use App\Domain\Inventory\Model\Account;
use App\Domain\Pokemon\Model\IvSpread;
use App\Domain\Pvp\Model\Cp;
use App\Domain\Pvp\Model\League;

final readonly class AddToInventoryCommand
{
    /**
     * @param IvSpread|positive-int $ivs the spread, or its rank as the form the league ranks best
     * @param ?Cp $cp the CP shown in the game, to refuse a Pokémon powered up past what its league allows
     */
    public function __construct(
        public string $identifier,
        public IvSpread|int $ivs,
        public League $league,
        public ?Cp $cp = null,
        public bool $shadow = false,
        public Account $account = new Account(Account::DEFAULT),
    ) {}
}
