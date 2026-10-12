<?php

declare(strict_types=1);

namespace App\Infrastructure\OpenMeteo;

use App\Domain\Weather\Model\GameWeather;

/**
 * Translates one hour of WMO weather code and wind into the weather the game will show.
 *
 * The game reads AccuWeather, which no longer has a free plan; this forecast comes from
 * another model, so an hour can disagree with the game. The table is our own, drawn by
 * analogy with the community's AccuWeather translation (thornleaf/pgo-weatherbot): skies
 * map one to one, precipitation wins over cloud, and only dry skies can turn Windy, past
 * 24 km/h of wind or 35 km/h of gusts. Nobody has checked it against the game yet — like
 * the mega move multiplier, it is a value no upstream file can confirm.
 */
final readonly class WmoConditions
{
    /**
     * WMO code (as used by Open-Meteo) => [game weather, whether strong wind can turn it
     * Windy]. Codes missing here are not part of the WMO table Open-Meteo returns.
     */
    private const array CODES = [
        0 => ['clear', true], // Clear sky
        1 => ['clear', true], // Mainly clear
        2 => ['partly_cloudy', true], // Partly cloudy
        3 => ['overcast', true], // Overcast
        45 => ['fog', false], // Fog
        48 => ['fog', false], // Depositing rime fog
        51 => ['rainy', false], // Drizzle: light
        53 => ['rainy', false], // Drizzle: moderate
        55 => ['rainy', false], // Drizzle: dense
        56 => ['rainy', false], // Freezing drizzle: light
        57 => ['rainy', false], // Freezing drizzle: dense
        61 => ['rainy', false], // Rain: slight
        63 => ['rainy', false], // Rain: moderate
        65 => ['rainy', false], // Rain: heavy
        66 => ['rainy', false], // Freezing rain: light
        67 => ['rainy', false], // Freezing rain: heavy
        71 => ['snow', false], // Snow fall: slight
        73 => ['snow', false], // Snow fall: moderate
        75 => ['snow', false], // Snow fall: heavy
        77 => ['snow', false], // Snow grains
        80 => ['rainy', false], // Rain showers: slight
        81 => ['rainy', false], // Rain showers: moderate
        82 => ['rainy', false], // Rain showers: violent
        85 => ['snow', false], // Snow showers: slight
        86 => ['snow', false], // Snow showers: heavy
        95 => ['rainy', false], // Thunderstorm
        96 => ['rainy', false], // Thunderstorm with slight hail
        99 => ['rainy', false], // Thunderstorm with heavy hail
    ];

    /** Above either of these, a dry sky turns Windy in game (km/h). */
    private const float WIND_SPEED_KMH = 24.0;
    private const float WIND_GUST_KMH = 35.0;

    public function translate(int $code, float $windKmh, float $gustKmh): ?GameWeather
    {
        $entry = self::CODES[$code] ?? null;

        if (null === $entry) {
            return null;
        }

        [$weather, $windyAble] = $entry;

        if ($windyAble && ($windKmh > self::WIND_SPEED_KMH || $gustKmh > self::WIND_GUST_KMH)) {
            return GameWeather::Windy;
        }

        return GameWeather::from($weather);
    }
}
