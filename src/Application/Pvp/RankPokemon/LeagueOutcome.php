<?php

declare(strict_types=1);

namespace App\Application\Pvp\RankPokemon;

use App\Domain\Pvp\Model\League;
use App\Domain\Pvp\Model\RankedSpread;
use App\Domain\Pvp\Model\SpeciesRank;

final readonly class LeagueOutcome
{
    public function __construct(
        public League $league,
        public ?SpeciesRank $speciesRank,
        public ?RankedSpread $spread,
        public bool $ineligible = false,
    ) {}
}
