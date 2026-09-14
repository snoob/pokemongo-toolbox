<?php

declare(strict_types=1);

namespace App\Domain\Pvp\Model;

/**
 * Levels move in half steps. 50 is the regular ceiling, 51 is reachable as a best buddy.
 */
final readonly class PokemonLevel implements \Stringable
{
    public const float MIN = 1.0;
    public const float REGULAR_CAP = 50.0;
    public const float BEST_BUDDY_CAP = 51.0;

    public function __construct(
        public float $value,
    ) {
        if ($value < self::MIN || $value > self::BEST_BUDDY_CAP) {
            throw new \InvalidArgumentException(\sprintf(
                'Level must be between %.1f and %.1f, got %.1f.',
                self::MIN,
                self::BEST_BUDDY_CAP,
                $value,
            ));
        }

        if (0.0 !== fmod($value * 2.0, 1.0)) {
            throw new \InvalidArgumentException(\sprintf('Level must be a multiple of 0.5, got %.2f.', $value));
        }
    }

    public static function regularCap(): self
    {
        return new self(self::REGULAR_CAP);
    }

    public static function bestBuddyCap(): self
    {
        return new self(self::BEST_BUDDY_CAP);
    }

    /**
     * Every reachable level up to $cap, highest first — the order a "strongest level
     * under the CP cap" search wants.
     *
     * @return non-empty-list<self>
     */
    public static function descendingTo(self $cap): array
    {
        $levels = [];

        for ($value = $cap->value; $value >= self::MIN; $value -= 0.5) {
            $levels[] = new self($value);
        }

        return $levels;
    }

    #[\Override]
    public function __toString(): string
    {
        return rtrim(rtrim(number_format($this->value, 1, '.', ''), '0'), '.');
    }
}
