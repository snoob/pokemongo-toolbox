<?php

declare(strict_types=1);

namespace App\Domain\Weather\Port;

use App\Domain\Weather\Exception\PlaceNotFound;
use App\Domain\Weather\Exception\WeatherUnavailable;
use App\Domain\Weather\Model\Place;

interface PlaceSearch
{
    /**
     * The best match for a place typed by the player, e.g. "Villemomble, France".
     *
     * @throws PlaceNotFound
     * @throws WeatherUnavailable
     */
    public function find(string $query): Place;
}
