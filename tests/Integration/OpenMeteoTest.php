<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Domain\Weather\Exception\PlaceNotFound;
use App\Domain\Weather\Exception\WeatherUnavailable;
use App\Domain\Weather\Model\GameWeather;
use App\Domain\Weather\Model\HourlyWeather;
use App\Domain\Weather\Model\Place;
use App\Infrastructure\Json\HttpJsonFetcher;
use App\Infrastructure\OpenMeteo\OpenMeteoForecast;
use App\Infrastructure\OpenMeteo\OpenMeteoPlaceSearch;
use App\Infrastructure\OpenMeteo\WmoConditions;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Runs against frozen extracts of Open-Meteo's answers, shaped as the API returns them.
 */
final class OpenMeteoTest extends TestCase
{
    /** @var list<string> */
    private array $urls = [];

    #[\Override]
    protected function setUp(): void
    {
        $this->urls = [];
    }

    public function testTheForecastAsksForTheSeriesThatDecideTheGameWeather(): void
    {
        $this->forecast()->hourly(new Place('Villemomble, France', 48.8834, 2.5));

        $url = $this->url(0);

        self::assertStringContainsString('latitude=48.883&longitude=2.500', $url, 'rounded so the cache serves');
        self::assertStringContainsString('hourly=weather_code%2Cwind_speed_10m%2Cwind_gusts_10m', $url);
        self::assertStringContainsString('forecast_hours=12', $url);
    }

    public function testEachHourKeepsThePlacesLocalClockAndItsGameWeather(): void
    {
        $hours = $this->forecast()->hourly(new Place('Villemomble, France', 48.883, 2.5));

        self::assertSame(
            ['2026-10-10T15:00:00+02:00', '2026-10-10T16:00:00+02:00', '2026-10-10T17:00:00+02:00'],
            array_map(static fn(HourlyWeather $h): string => $h->hour->format(\DATE_ATOM), $hours),
        );
        self::assertSame(
            [GameWeather::PartlyCloudy, GameWeather::Windy, null],
            array_map(static fn(HourlyWeather $h): ?GameWeather => $h->weather, $hours),
            'overcast with 40 km/h gusts is Windy; a gap in the series is an unknown hour, not a shifted one',
        );
    }

    public function testAnUnreachableForecastIsReportedAsUnavailable(): void
    {
        $this->expectException(WeatherUnavailable::class);

        $this->forecast(status: 503)->hourly(new Place('Villemomble, France', 48.883, 2.5));
    }

    public function testATypedPlaceResolvesToTheGeocodersFirstMatch(): void
    {
        $place = $this->search()->find('Paris');

        self::assertSame('Paris, France', $place->name);
        self::assertStringContainsString('name=Paris&count=10', $this->url(0), 'only the name goes to the geocoder');
    }

    public function testTheCountryAfterTheCommaPicksAmongNamesakes(): void
    {
        self::assertSame('Paris, United States', $this->search()->find('Paris, United States')->name);
        self::assertSame('Paris, United States', $this->search()->find('Paris, us')->name, 'a country code works too');
    }

    public function testAPlaceNobodyKnowsIsReportedAsSuch(): void
    {
        $this->expectException(PlaceNotFound::class);

        $this->search()->find('Atlantis');
    }

    public function testACountryWithoutThatTownIsNotAnswerForByAnotherOne(): void
    {
        $this->expectException(PlaceNotFound::class);

        $this->search()->find('Paris, Japan');
    }

    private function url(int $index): string
    {
        return $this->urls[$index] ?? self::fail(\sprintf('no request #%d was made', $index));
    }

    private function forecast(int $status = 200): OpenMeteoForecast
    {
        return new OpenMeteoForecast($this->fetcher($status), new WmoConditions());
    }

    private function search(): OpenMeteoPlaceSearch
    {
        return new OpenMeteoPlaceSearch($this->fetcher());
    }

    private function fetcher(int $status = 200): HttpJsonFetcher
    {
        return new HttpJsonFetcher(new MockHttpClient(function (string $_method, string $url) use (
            $status,
        ): MockResponse {
            $this->urls[] = $url;

            return (
                200 === $status
                    ? new MockResponse($this->answerFor($url))
                    : new MockResponse('{"error":true}', ['http_code' => $status])
            );
        }));
    }

    private function answerFor(string $url): string
    {
        $body = match (true) {
            str_contains($url, 'name=Atlantis') => ['generationtime_ms' => 0.2],
            str_contains($url, 'geocoding-api') => [
                'results' => [
                    [
                        'name' => 'Paris',
                        'country' => 'France',
                        'country_code' => 'FR',
                        'latitude' => 48.85,
                        'longitude' => 2.35,
                    ],
                    [
                        'name' => 'Paris',
                        'country' => 'United States',
                        'country_code' => 'US',
                        'latitude' => 33.66,
                        'longitude' => -95.56,
                    ],
                ],
            ],
            str_contains($url, '/v1/forecast') => [
                'timezone' => 'Europe/Paris',
                'utc_offset_seconds' => 7200,
                'hourly' => [
                    'time' => [1_791_637_200, 1_791_640_800, 1_791_644_400],
                    'weather_code' => [2, 3, null],
                    'wind_speed_10m' => [12.0, 18.5, 30.0],
                    'wind_gusts_10m' => [20.0, 40.0, 50.0],
                ],
            ],
            default => self::fail('unexpected call to ' . $url),
        };

        return json_encode($body, \JSON_THROW_ON_ERROR);
    }
}
