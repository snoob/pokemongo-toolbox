<?php

declare(strict_types=1);

namespace App\Domain\Pokemon\Model;

/**
 * A charged or fast attack, with the two numbers that decide its worth in PvP: how hard
 * it hits and what it costs to fire.
 */
final readonly class Move
{
    public function __construct(
        public MoveId $id,
        public int $power,
        public int $energy,
        public bool $megaExclusive = false,
    ) {}

    /**
     * Damage per energy — the usual way to compare two charged attacks.
     */
    public function damagePerEnergy(): float
    {
        return 0 === $this->energy ? 0.0 : $this->power / $this->energy;
    }
}
