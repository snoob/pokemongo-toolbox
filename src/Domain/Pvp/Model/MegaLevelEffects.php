<?php

declare(strict_types=1);

namespace App\Domain\Pvp\Model;

/**
 * What a Mega Level grants.
 *
 * Two things matter to PvP: `additionalLevels`, only at the last level, where the mega
 * fights that many Pokémon levels above where it sits; and `movePowerMultiplier`, which
 * lifts the base power of the mega-exclusive Charged Attack at every level. The rest is
 * about how often you can evolve and what you get for catching.
 */
final readonly class MegaLevelEffects
{
    public function __construct(
        public int $additionalLevels,
        public int $cooldownDays,
        public int $extraCandy,
        public float $movePowerMultiplier = 1.0,
    ) {}

    /**
     * Whether the level actually lifts the exclusive move's power — Base Level does not.
     */
    public function boostsTheExclusiveMove(): bool
    {
        return $this->movePowerMultiplier > 1.0;
    }
}
