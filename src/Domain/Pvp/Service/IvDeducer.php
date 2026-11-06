<?php

declare(strict_types=1);

namespace App\Domain\Pvp\Service;

use App\Domain\Pokemon\Model\BaseStats;
use App\Domain\Pokemon\Model\IvPattern;
use App\Domain\Pokemon\Model\IvSpread;
use App\Domain\Pvp\Exception\IvMismatch;
use App\Domain\Pvp\Model\Cp;
use App\Domain\Pvp\Model\IvDeduction;
use App\Domain\Pvp\Model\PokemonLevel;
use App\Domain\Pvp\Port\CpMultiplierTable;

/**
 * Settles the IVs from the CP the game shows. The level is unknown, but few levels give
 * that exact CP to a given spread, so trying them all — 4096 spreads × 101 levels at
 * worst — is both exhaustive and instant.
 */
final readonly class IvDeducer
{
    public function __construct(
        private CpCalculator $cp,
        private CpMultiplierTable $multipliers,
    ) {}

    /**
     * @throws IvMismatch when no spread or several give that CP
     */
    public function deduce(BaseStats $stats, IvPattern $pattern, Cp $cp, PokemonLevel $levelCap): IvDeduction
    {
        $multipliers = [];

        foreach (array_reverse(PokemonLevel::descendingTo($levelCap)) as $level) {
            $multipliers[] = [$level, $this->multipliers->multiplierFor($level)];
        }

        $candidates = [];

        foreach (IvSpread::all() as $iv) {
            if (!$pattern->matches($iv)) {
                continue;
            }

            $levels = array_values(array_filter(
                $multipliers,
                /** @param array{PokemonLevel, float} $entry */
                fn(array $entry): bool => $this->cp->cpFrom($stats, $iv, $entry[1])->value === $cp->value,
            ));

            if ([] === $levels) {
                continue;
            }

            $candidates[] = new IvDeduction(
                $iv,
                $cp,
                array_map(
                    /** @param array{PokemonLevel, float} $entry */
                    static fn(array $entry): PokemonLevel => $entry[0],
                    $levels,
                ),
                !$pattern->isComplete(),
            );
        }

        if ([] === $candidates) {
            throw IvMismatch::none($pattern, $cp);
        }

        if (\count($candidates) > 1) {
            throw IvMismatch::ambiguous($pattern, $cp, $candidates);
        }

        return $candidates[0];
    }
}
