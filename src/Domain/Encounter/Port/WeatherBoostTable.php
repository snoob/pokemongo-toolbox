<?php

declare(strict_types=1);

namespace App\Domain\Encounter\Port;

use App\Domain\Pokemon\Model\PokemonType;
use App\Domain\Weather\Model\GameWeather;

interface WeatherBoostTable
{
    /**
     * @return list<PokemonType>
     */
    public function boostedTypes(GameWeather $weather): array;
}
