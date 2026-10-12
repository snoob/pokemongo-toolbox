<?php

declare(strict_types=1);

namespace App\Domain\Weather\Port;

use App\Domain\Weather\Exception\WeatherUnavailable;
use App\Domain\Weather\Model\HourlyWeather;
use App\Domain\Weather\Model\Place;

interface WeatherForecast
{
    /**
     * The coming hours, earliest first, already translated into game weather.
     *
     * @return list<HourlyWeather>
     *
     * @throws WeatherUnavailable
     */
    public function hourly(Place $place): array;
}
