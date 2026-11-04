<?php

declare(strict_types=1);

namespace App\Application\Inventory;

use App\Domain\Pokemon\Model\Species;
use App\Domain\Pvp\Model\RankedSpread;
use App\Domain\Pvp\Model\SpeciesRank;

/**
 * What a kept Pokémon is worth in its league: the form of its family the league ranks
 * best — Dragonair rather than Dragonite in the Great League — and where its IVs place
 * as that form. `spread` is null when that form cannot get under the league's CP cap.
 */
final readonly class Standing
{
    public function __construct(
        public Species $form,
        public ?SpeciesRank $speciesRank,
        public ?RankedSpread $spread,
    ) {}
}
