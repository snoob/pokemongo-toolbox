<?php

declare(strict_types=1);

namespace App\Domain\Encounter\Service;

use App\Domain\Encounter\Port\WeatherBoostTable;
use App\Domain\Pokemon\Model\Species;
use App\Domain\Weather\Model\GameWeather;

/**
 * A wild Pokémon is boosted when either of its types is favoured by the current weather.
 * The boost raises its IV floor to 4, which is not always what a PvP player wants: the
 * best capped-league spreads usually need an attack IV below that floor.
 */
final readonly class WeatherBoost
{
    public function __construct(
        private WeatherBoostTable $table,
    ) {}

    public function boosts(GameWeather $weather, Species $species): bool
    {
        foreach ($this->table->boostedTypes($weather) as $type) {
            if ($species->hasType($type)) {
                return true;
            }
        }

        return false;
    }
}
