<?php

declare(strict_types=1);

namespace App\Application\Inventory\CheckInventory;

use App\Domain\Inventory\Model\Account;
use App\Domain\Pokemon\Model\IvFloor;

/**
 * Every kept Pokémon of the leagues checked, in the order of `inventory:list`.
 */
final readonly class CheckInventoryResult
{
    /**
     * @param list<CheckedPokemon> $checked
     */
    public function __construct(
        /** @var list<CheckedPokemon> */
        public array $checked,
        public IvFloor $floor,
        public Account $account,
    ) {}
}
