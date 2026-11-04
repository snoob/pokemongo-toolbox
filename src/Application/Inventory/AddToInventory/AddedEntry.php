<?php

declare(strict_types=1);

namespace App\Application\Inventory\AddToInventory;

use App\Application\Inventory\KeptPokemon;
use App\Application\Inventory\Standing;
use App\Domain\Inventory\Model\InventoryEntry;

/**
 * An entry just added, what it is worth in its league, and the best Pokémon already kept
 * there that beats it as the same form — null when none does.
 */
final readonly class AddedEntry
{
    public function __construct(
        public InventoryEntry $entry,
        public Standing $standing,
        public ?KeptPokemon $better = null,
    ) {}
}
