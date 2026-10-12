<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use App\Domain\Weather\Exception\WeatherUnavailable;
use App\Domain\Weather\Model\HourlyWeather;
use App\Domain\Weather\Model\Place;
use App\Domain\Weather\Port\WeatherForecast;

final class InMemoryWeatherForecast implements WeatherForecast
{
    public ?Place $askedFor = null;

    /**
     * @param list<HourlyWeather> $hours
     */
    public function __construct(
        private readonly array $hours = [],
        private readonly bool $unavailable = false,
    ) {}

    #[\Override]
    public function hourly(Place $place): array
    {
        $this->askedFor = $place;

        if ($this->unavailable) {
            throw WeatherUnavailable::because('unreachable');
        }

        return $this->hours;
    }
}
