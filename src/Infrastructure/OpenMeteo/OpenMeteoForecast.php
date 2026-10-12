<?php

declare(strict_types=1);

namespace App\Infrastructure\OpenMeteo;

use App\Domain\Weather\Exception\WeatherUnavailable;
use App\Domain\Weather\Model\HourlyWeather;
use App\Domain\Weather\Model\Place;
use App\Domain\Weather\Port\WeatherForecast;
use App\Infrastructure\Json\JsonFetcher;
use App\Infrastructure\Json\JsonFetchFailed;
use App\Infrastructure\Json\JsonPath;
use App\Infrastructure\Json\JsonValue;

/**
 * Hourly forecast from Open-Meteo: free, no key, and gusts included — the only way to
 * tell Windy apart.
 */
final readonly class OpenMeteoForecast implements WeatherForecast
{
    private const string URL = 'https://api.open-meteo.com/v1/forecast';
    private const int HOURS = 12;

    public function __construct(
        private JsonFetcher $fetcher,
        private WmoConditions $conditions,
    ) {}

    #[\Override]
    public function hourly(Place $place): array
    {
        $answer = $this->fetch($place);
        $hourly = JsonPath::arrayAt($answer, 'hourly');

        // The series are parallel arrays; narrowing each one with array_map keeps their
        // indexes aligned even where the source has a gap (null).
        $times = array_map(JsonValue::asInt(...), JsonPath::arrayAt($hourly, 'time') ?? []);
        $codes = array_map(JsonValue::asInt(...), JsonPath::arrayAt($hourly, 'weather_code') ?? []);
        $winds = array_map(JsonValue::asFloatOrNull(...), JsonPath::arrayAt($hourly, 'wind_speed_10m') ?? []);
        $gusts = array_map(JsonValue::asFloatOrNull(...), JsonPath::arrayAt($hourly, 'wind_gusts_10m') ?? []);
        $zone = $this->timezone(JsonPath::stringAt($answer, 'timezone'));
        $hours = [];

        foreach ($times as $index => $epoch) {
            if (null === $epoch) {
                continue;
            }

            $code = $codes[$index] ?? null;
            $hours[] = new HourlyWeather(
                new \DateTimeImmutable('@' . $epoch)->setTimezone($zone),
                null === $code
                    ? null
                    : $this->conditions->translate($code, $winds[$index] ?? 0.0, $gusts[$index] ?? 0.0),
            );
        }

        if ([] === $hours) {
            throw WeatherUnavailable::because('Open-Meteo returned no hourly forecast.');
        }

        return $hours;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function fetch(Place $place): array
    {
        // Rounded to about a hundred metres, so a few metres of difference in the guess
        // still hit the cache.
        $url =
            self::URL
            . '?'
            . http_build_query([
                'latitude' => \sprintf('%.3F', $place->latitude),
                'longitude' => \sprintf('%.3F', $place->longitude),
                'hourly' => 'weather_code,wind_speed_10m,wind_gusts_10m',
                'wind_speed_unit' => 'kmh',
                'forecast_hours' => (string) self::HOURS,
                'timezone' => 'auto',
                'timeformat' => 'unixtime',
            ]);

        try {
            return $this->fetcher->fetch($url);
        } catch (JsonFetchFailed $e) {
            throw WeatherUnavailable::because($e->getMessage(), $e);
        }
    }

    /**
     * The place's own zone, so the hours read as the player's clock does.
     */
    private function timezone(?string $name): \DateTimeZone
    {
        try {
            return new \DateTimeZone($name ?? 'UTC');
        } catch (\Exception) {
            return new \DateTimeZone('UTC');
        }
    }
}
