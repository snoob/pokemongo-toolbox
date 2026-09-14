<?php

declare(strict_types=1);

namespace App\Domain\Pvp\Model;

final readonly class Cp
{
    /** A Pokémon never shows less than 10 CP, however weak its stats. */
    public const int FLOOR = 10;

    public function __construct(
        public int $value,
    ) {
        if ($value < self::FLOOR) {
            throw new \InvalidArgumentException(\sprintf('CP cannot go below %d, got %d.', self::FLOOR, $value));
        }
    }

    public function exceeds(int $cap): bool
    {
        return $this->value > $cap;
    }
}
