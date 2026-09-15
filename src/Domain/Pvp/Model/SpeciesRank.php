<?php

declare(strict_types=1);

namespace App\Domain\Pvp\Model;

/**
 * Where a species sits in a league's meta ranking, as published by the ranking source.
 */
final readonly class SpeciesRank
{
    public function __construct(
        public League $league,
        public int $position,
        public int $total,
        public float $score,
        public ?Moveset $moveset = null,
    ) {
        if ($position < 1) {
            throw new \InvalidArgumentException(\sprintf('A rank position starts at 1, got %d.', $position));
        }

        if ($total < $position) {
            throw new \InvalidArgumentException(\sprintf(
                'Rank position %d cannot exceed the ranking size %d.',
                $position,
                $total,
            ));
        }
    }
}
