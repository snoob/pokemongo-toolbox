<?php

declare(strict_types=1);

namespace App\Domain\Pvp\Model;

/**
 * How far a Mega Evolution has been levelled up. Only the top level changes anything
 * a PvP player cares about: the mega then fights as if it were a couple of Pokémon
 * levels higher, so the CP to stop at before evolving drops.
 */
final readonly class MegaLevel
{
    public const int MIN = 1;
    public const int MAX = 4;

    /** The level a PvP player realistically targets, and what a bare "--mega" means. */
    public const int DEFAULT = 3;

    public function __construct(
        public int $value,
    ) {
        if ($value < self::MIN || $value > self::MAX) {
            throw new \InvalidArgumentException(\sprintf(
                'A Mega Level runs from %d to %d, got %d.',
                self::MIN,
                self::MAX,
                $value,
            ));
        }
    }

    public static function default(): self
    {
        return new self(self::DEFAULT);
    }
}
