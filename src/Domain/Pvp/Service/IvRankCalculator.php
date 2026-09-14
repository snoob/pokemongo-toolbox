<?php

declare(strict_types=1);

namespace App\Domain\Pvp\Service;

use App\Domain\Pokemon\Model\BaseStats;
use App\Domain\Pokemon\Model\IvSpread;
use App\Domain\Pvp\Exception\SpeciesIneligibleForLeague;
use App\Domain\Pvp\Model\Cp;
use App\Domain\Pvp\Model\IvRanking;
use App\Domain\Pvp\Model\League;
use App\Domain\Pvp\Model\PokemonLevel;
use App\Domain\Pvp\Model\RankedSpread;
use App\Domain\Pvp\Port\CpMultiplierTable;

/**
 * Ranks the 4096 IV spreads of one species against each other for a league:
 * each spread is pushed to the highest level that stays under the CP cap, then
 * spreads are ordered by stat product.
 */
final readonly class IvRankCalculator
{
    public function __construct(
        private CpCalculator $cp,
        private CpMultiplierTable $multipliers,
    ) {}

    public function rank(BaseStats $stats, League $league, PokemonLevel $levelCap): IvRanking
    {
        $levels = PokemonLevel::descendingTo($levelCap);

        // The multiplier lookup is the hot path: 4096 spreads × up to 101 levels.
        $steps = [];
        foreach ($levels as $level) {
            $steps[] = [$level, $this->multipliers->multiplierFor($level)];
        }

        $cpCap = $league->cpCap();

        /** @var list<array{iv: IvSpread, level: PokemonLevel, cp: Cp, statProduct: float}> $ranked */
        $ranked = [];

        foreach (IvSpread::all() as $iv) {
            $reached = $this->strongestLevelWithin($stats, $iv, $steps, $cpCap);

            if (null === $reached) {
                continue;
            }

            [$level, $multiplier, $cp] = $reached;

            $ranked[] = [
                'iv' => $iv,
                'level' => $level,
                'cp' => $cp,
                'statProduct' => $this->cp->statProductFrom($stats, $iv, $multiplier),
            ];
        }

        if ([] === $ranked) {
            throw new SpeciesIneligibleForLeague($league);
        }

        // Stable sort: equal stat products keep the deterministic IvSpread::all() order.
        usort(
            $ranked,
            /**
             * @param array{iv: IvSpread, level: PokemonLevel, cp: Cp, statProduct: float} $a
             * @param array{iv: IvSpread, level: PokemonLevel, cp: Cp, statProduct: float} $b
             */
            static fn(array $a, array $b): int => $b['statProduct'] <=> $a['statProduct'],
        );

        $best = $ranked[0]['statProduct'];
        $total = \count($ranked);
        $spreads = [];

        foreach ($ranked as $index => $entry) {
            $spreads[] = new RankedSpread(
                iv: $entry['iv'],
                position: $index + 1,
                total: $total,
                statProduct: $entry['statProduct'],
                percentOfBest: $best > 0.0 ? ($entry['statProduct'] / $best) * 100 : 100.0,
                cp: $entry['cp'],
                level: $entry['level'],
            );
        }

        return new IvRanking($league, $spreads);
    }

    /**
     * @param non-empty-list<array{PokemonLevel, float}> $steps highest level first
     *
     * @return array{PokemonLevel, float, Cp}|null null when even level 1 busts the cap
     */
    private function strongestLevelWithin(BaseStats $stats, IvSpread $iv, array $steps, ?int $cpCap): ?array
    {
        foreach ($steps as [$level, $multiplier]) {
            $cp = $this->cp->cpFrom($stats, $iv, $multiplier);

            if (null === $cpCap || !$cp->exceeds($cpCap)) {
                return [$level, $multiplier, $cp];
            }
        }

        return null;
    }
}
