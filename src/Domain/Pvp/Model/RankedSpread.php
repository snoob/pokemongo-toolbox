<?php

declare(strict_types=1);

namespace App\Domain\Pvp\Model;

use App\Domain\Pokemon\Model\IvSpread;

/**
 * One IV spread, placed among every spread of the same species for a given league.
 */
final readonly class RankedSpread
{
    public function __construct(
        public IvSpread $iv,
        public int $position,
        public int $total,
        public float $statProduct,
        public float $percentOfBest,
        public Cp $cp,
        public PokemonLevel $level,
    ) {}

    public function isPerfect(): bool
    {
        return 1 === $this->position;
    }
}
