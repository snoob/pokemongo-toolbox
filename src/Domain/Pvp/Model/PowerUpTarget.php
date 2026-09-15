<?php

declare(strict_types=1);

namespace App\Domain\Pvp\Model;

/**
 * Where to stop powering up: the CP the game will show, and the level it corresponds to.
 *
 * The two travel together — a CP alone cannot be checked while powering up, and a level
 * alone is not what the game displays.
 *
 * `megaGainPercent` is the step from this CP to the one the mega actually shows — stat
 * gain plus, at the last Mega Level, the two free levels. It belongs here rather than
 * beside the species because it shifts with the IVs: the base and mega forms have
 * different base stats, so the same IVs weigh differently on each — six points apart
 * between a 0/8/15 and a 15/15/15 Altaria.
 */
final readonly class PowerUpTarget
{
    public function __construct(
        public Cp $cp,
        public PokemonLevel $level,
        public int $megaGainPercent = 0,
    ) {}
}
