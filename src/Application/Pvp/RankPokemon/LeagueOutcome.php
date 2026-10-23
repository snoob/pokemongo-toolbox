<?php

declare(strict_types=1);

namespace App\Application\Pvp\RankPokemon;

use App\Domain\Pvp\Model\League;
use App\Domain\Pvp\Model\PowerUpTarget;
use App\Domain\Pvp\Model\RankedSpread;
use App\Domain\Pvp\Model\RankWithinFloor;
use App\Domain\Pvp\Model\SpeciesRank;

/**
 * `best` is the best spread the IV floor leaves possible, and `withinFloor` where the
 * given spread stands among those; both rankings agree when there is no floor. A league
 * is `ineligible` when no spread above the floor fits under its CP cap.
 */
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
        public ?RankWithinFloor $withinFloor = null,
    ) {}
}
