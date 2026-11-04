<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Model;

use App\Domain\Pokemon\Model\IvSpread;
use App\Domain\Pokemon\Model\SpeciesId;
use App\Domain\Pvp\Model\League;

/**
 * One Pokémon kept for one league. The same Pokémon kept for two leagues is two entries:
 * each league is its own team, the Mega Editions included.
 *
 * The species is the form in hand — a Dratini kept to become a Dragonair — and its id
 * carries the shadow flag, as everywhere else.
 */
final readonly class InventoryEntry
{
    public function __construct(
        public int $id,
        public SpeciesId $species,
        public IvSpread $iv,
        public League $league,
        public \DateTimeImmutable $addedAt,
    ) {
        if ($id < 1) {
            throw new \InvalidArgumentException(\sprintf('An inventory entry id starts at 1, got %d.', $id));
        }
    }

    public function isSameAs(SpeciesId $species, IvSpread $iv, League $league): bool
    {
        return $this->species->equals($species) && $this->iv->equals($iv) && $this->league === $league;
    }
}
