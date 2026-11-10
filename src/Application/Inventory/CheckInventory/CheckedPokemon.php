<?php

declare(strict_types=1);

namespace App\Application\Inventory\CheckInventory;

use App\Application\Inventory\ListInventory\InventoryLine;
use App\Domain\Pvp\Model\TradeOdds;

/**
 * A kept Pokémon and what a trade could make of it; `odds` is null when it cannot be
 * weighed — a form the game data no longer knows, or that busts the league's cap, or a
 * floor that leaves nothing under it.
 */
final readonly class CheckedPokemon
{
    public function __construct(
        public InventoryLine $line,
        public ?TradeOdds $odds,
    ) {}
}
