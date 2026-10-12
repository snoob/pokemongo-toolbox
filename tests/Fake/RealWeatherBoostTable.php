<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use App\Domain\Encounter\Port\WeatherBoostTable;
use App\Domain\Pokemon\Model\PokemonType;
use App\Domain\Weather\Model\GameWeather;

/**
 * The game's own affinities, copied from the game master
 * (WEATHER_AFFINITY_*), so tests run on true values.
 */
final readonly class RealWeatherBoostTable implements WeatherBoostTable
{
    #[\Override]
    public function boostedTypes(GameWeather $weather): array
    {
        return match ($weather) {
            GameWeather::Clear => [PokemonType::Fire, PokemonType::Grass, PokemonType::Ground],
            GameWeather::PartlyCloudy => [PokemonType::Normal, PokemonType::Rock],
            GameWeather::Overcast => [PokemonType::Fairy, PokemonType::Fighting, PokemonType::Poison],
            GameWeather::Rainy => [PokemonType::Bug, PokemonType::Electric, PokemonType::Water],
            GameWeather::Windy => [PokemonType::Dragon, PokemonType::Flying, PokemonType::Psychic],
            GameWeather::Snow => [PokemonType::Ice, PokemonType::Steel],
            GameWeather::Fog => [PokemonType::Dark, PokemonType::Ghost],
        };
    }
}
