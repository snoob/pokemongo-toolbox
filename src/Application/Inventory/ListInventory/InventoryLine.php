<?php

declare(strict_types=1);

namespace App\Application\Inventory\ListInventory;

use App\Application\Inventory\Standing;
use App\Domain\Inventory\Model\InventoryEntry;
use App\Domain\Pokemon\Model\Species;
use App\Domain\Pvp\Model\League;

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

    /**
     * Its place by rank: league in the order of `League::cases()`, then the form's place
     * in the league, then the IVs' place as that form. Unranked forms and spreads that
     * bust the cap sink to the bottom of their league.
     *
     * @return list<int>
     */
    public function rankOrder(): array
    {
        return [
            (int) array_search($this->entry->league, League::cases(), true),
            $this->standing->speciesRank->position ?? \PHP_INT_MAX,
            $this->standing->spread->position ?? \PHP_INT_MAX,
        ];
    }
}
