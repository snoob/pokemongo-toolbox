<?php

declare(strict_types=1);

namespace App\Application\Inventory;

use App\Domain\Inventory\Model\InventoryEntry;
use App\Domain\Pokemon\Model\Species;
use App\Domain\Pvp\Model\RankedSpread;

/**
 * An entry already in the inventory, placed as the form another one is compared on.
 */
final readonly class KeptPokemon
{
    public function __construct(
        public InventoryEntry $entry,
        public Species $species,
        public RankedSpread $spread,
    ) {}
}
