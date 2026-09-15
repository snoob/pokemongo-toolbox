<?php

declare(strict_types=1);

namespace App\Application\Pvp\RankPokemon;

use App\Domain\Pvp\Model\League;
use App\Domain\Pvp\Model\PowerUpTarget;
use App\Domain\Pvp\Model\RankedSpread;
use App\Domain\Pvp\Model\SpeciesRank;

final readonly class LeagueOutcome
{
    public function __construct(
        public League $league,
        public ?SpeciesRank $speciesRank,
        public ?RankedSpread $spread,
        public ?RankedSpread $best = null,
        public ?PowerUpTarget $powerUpTo = null,
        public ?PowerUpTarget $bestPowerUpTo = null,
        public bool $ineligible = false,
    ) {}
}
