<?php

declare(strict_types=1);

namespace App\Application\Inventory\ListInventory;

use App\Application\Inventory\Standing;
use App\Domain\Inventory\Model\InventoryEntry;
use App\Domain\Pokemon\Model\Species;

/**
 * One kept Pokémon and what it is worth in its league. `species` and `standing` are null
 * when the game data no longer knows the form — renamed or dropped by an update.
 */
final readonly class InventoryLine
{
    public function __construct(
        public InventoryEntry $entry,
        public ?Species $species,
        public ?Standing $standing,
    ) {}
}
