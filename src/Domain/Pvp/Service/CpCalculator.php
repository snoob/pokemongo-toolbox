<?php

declare(strict_types=1);

namespace App\Domain\Pvp\Service;

use App\Domain\Pokemon\Model\BaseStats;
use App\Domain\Pokemon\Model\IvSpread;
use App\Domain\Pvp\Model\Cp;
use App\Domain\Pvp\Model\PokemonLevel;
use App\Domain\Pvp\Port\CpMultiplierTable;

/**
 * The two Pokémon GO formulas everything else is built on. Stateless by design.
 */
final readonly class CpCalculator
{
    public function __construct(
        private CpMultiplierTable $multipliers,
    ) {}

    public function cpAt(BaseStats $stats, IvSpread $iv, PokemonLevel $level): Cp
    {
        return $this->cpFrom($stats, $iv, $this->multipliers->multiplierFor($level));
    }

    public function statProductAt(BaseStats $stats, IvSpread $iv, PokemonLevel $level): float
    {
        return $this->statProductFrom($stats, $iv, $this->multipliers->multiplierFor($level));
    }

    /**
     * CP = floor( attack × √defense × √stamina × CPM² / 10 ), never below 10.
     * The division by 10 and the flooring are the game's, not a rounding choice of ours.
     */
    public function cpFrom(BaseStats $stats, IvSpread $iv, float $multiplier): Cp
    {
        $attack = $stats->attack + $iv->attack;
        $defense = $stats->defense + $iv->defense;
        $stamina = $stats->stamina + $iv->stamina;

        $cp = (int) floor(($attack * sqrt($defense) * sqrt($stamina) * ($multiplier ** 2)) / 10);

        return new Cp(max(Cp::FLOOR, $cp));
    }

    /**
     * Stat product = effective attack × effective defense × HP, where HP is floored
     * because the game shows whole hit points. It is the quantity PvP IV ranks sort on.
     */
    public function statProductFrom(BaseStats $stats, IvSpread $iv, float $multiplier): float
    {
        $attack = ($stats->attack + $iv->attack) * $multiplier;
        $defense = ($stats->defense + $iv->defense) * $multiplier;
        $hitPoints = floor(($stats->stamina + $iv->stamina) * $multiplier);

        return $attack * $defense * $hitPoints;
    }
}
