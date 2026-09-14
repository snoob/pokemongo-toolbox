<?php

declare(strict_types=1);

namespace App\Domain\Pvp\Model;

enum League: string
{
    case Great = 'great';
    case Ultra = 'ultra';
    case Master = 'master';

    /**
     * CP ceiling a Pokémon may not exceed to enter the league; null when uncapped.
     */
    public function cpCap(): ?int
    {
        return match ($this) {
            self::Great => 1500,
            self::Ultra => 2500,
            self::Master => null,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Great => 'Great League',
            self::Ultra => 'Ultra League',
            self::Master => 'Master League',
        };
    }

    /**
     * In an uncapped league every Pokémon reaches the level cap, so ranking IV spreads
     * by stat product degenerates into ordering raw IVs: 15/15/15 always wins.
     */
    public function ranksIvSpreads(): bool
    {
        return null !== $this->cpCap();
    }

    /** @return non-empty-list<self> */
    public static function all(): array
    {
        return self::cases();
    }
}
