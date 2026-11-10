<?php

declare(strict_types=1);

namespace App\Application\Inventory\ListInventory;

use App\Domain\Inventory\Model\Account;
use App\Domain\Pokemon\Model\IvSpread;
use App\Domain\Pvp\Model\League;

/**
 * What to list, each filter narrowing the one before: the league, the Pokémon — its
 * regular and shadow forms, or the shadow alone — and its IVs, or their rank, which only
 * a Pokémon and a league give a meaning.
 */
final readonly class ListInventoryQuery
{
    /**
     * @param ?League $league null lists every league
     * @param ?string $pokemon a name or a Pokédex number, resolved like `inventory:add`'s
     * @param IvSpread|positive-int|null $ivs a spread, or its rank as `inventory:add` reads it
     */
    public function __construct(
        public ?League $league = null,
        public Account $account = new Account(Account::DEFAULT),
        public ?string $pokemon = null,
        public bool $shadow = false,
        public IvSpread|int|null $ivs = null,
    ) {}
}
