<?php

declare(strict_types=1);

namespace App\Domain\Pvp\Model;

enum League: string
{
    case Great = 'great';
    case Ultra = 'ultra';
    case Master = 'master';

    // Mega Evolutions are barred from the standard leagues; since the Twilight Trails
    // season they have their own editions, where one mega per team is allowed.
    case MegaGreat = 'mega-great';
    case MegaUltra = 'mega-ultra';
    case MegaMaster = 'mega-master';

    /**
     * CP ceiling a Pokémon may not exceed to enter the league; null when uncapped.
     */
    public function cpCap(): ?int
    {
        return match ($this) {
            self::Great, self::MegaGreat => 1500,
            self::Ultra, self::MegaUltra => 2500,
            self::Master, self::MegaMaster => null,
        };
    }

    public function allowsMega(): bool
    {
        return match ($this) {
            self::MegaGreat, self::MegaUltra, self::MegaMaster => true,
            default => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Great => 'Great League',
            self::Ultra => 'Ultra League',
            self::Master => 'Master League',
            self::MegaGreat => 'Mega Great League',
            self::MegaUltra => 'Mega Ultra League',
            self::MegaMaster => 'Mega Master League',
        };
    }

    /** @return non-empty-list<self> */
    public static function standard(): array
    {
        return [self::Great, self::Ultra, self::Master];
    }

    /** @return non-empty-list<self> */
    public static function megaEditions(): array
    {
        return [self::MegaGreat, self::MegaUltra, self::MegaMaster];
    }

    /** @return non-empty-list<self> */
    public static function all(): array
    {
        return self::cases();
    }
}
