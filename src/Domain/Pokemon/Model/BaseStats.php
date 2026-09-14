<?php

declare(strict_types=1);

namespace App\Domain\Pokemon\Model;

/**
 * Pokémon GO base stats. These are *not* the main-series stats: GO uses its own
 * attack/defense/stamina scale, hence no conversion from a Pokédex API.
 */
final readonly class BaseStats
{
    public function __construct(
        public int $attack,
        public int $defense,
        public int $stamina,
    ) {
        foreach (['attack' => $attack, 'defense' => $defense, 'stamina' => $stamina] as $name => $value) {
            if ($value < 1) {
                throw new \InvalidArgumentException(\sprintf('Base %s must be 1 or greater, got %d.', $name, $value));
            }
        }
    }
}
