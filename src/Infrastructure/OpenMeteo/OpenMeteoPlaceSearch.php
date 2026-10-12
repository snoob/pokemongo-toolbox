<?php

declare(strict_types=1);

namespace App\Infrastructure\OpenMeteo;

use App\Domain\Weather\Exception\PlaceNotFound;
use App\Domain\Weather\Exception\WeatherUnavailable;
use App\Domain\Weather\Model\Place;
use App\Domain\Weather\Port\PlaceSearch;
use App\Infrastructure\Json\JsonFetcher;
use App\Infrastructure\Json\JsonFetchFailed;
use App\Infrastructure\Json\JsonPath;
use App\Infrastructure\Json\JsonValue;

/**
 * Looks a typed place up with Open-Meteo's geocoder.
 */
final readonly class OpenMeteoPlaceSearch implements PlaceSearch
{
    private const string URL = 'https://geocoding-api.open-meteo.com/v1/search';
    private const int CANDIDATES = 10;

    public function __construct(
        private JsonFetcher $fetcher,
    ) {}

    #[\Override]
    public function find(string $query): Place
    {
        $wanted = new PlaceQuery($query);

        if ('' === $wanted->name) {
            throw PlaceNotFound::forQuery($query);
        }

        // The geocoder ranks its matches by importance; the first is the one a person
        // would mean unless they named a country.
        foreach ($this->candidates($wanted->name) as $candidate) {
            if (!$wanted->accepts($candidate)) {
                continue;
            }

            $place = $this->toPlace($candidate);

            if (null !== $place) {
                return $place;
            }
        }

        throw PlaceNotFound::forQuery($query);
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    private function candidates(string $name): array
    {
        $url =
            self::URL
            . '?'
            . http_build_query([
                'name' => $name,
                'count' => (string) self::CANDIDATES,
                'language' => 'en',
                'format' => 'json',
            ]);

        try {
            return JsonPath::arrayListAt($this->fetcher->fetch($url), 'results');
        } catch (JsonFetchFailed $e) {
            throw WeatherUnavailable::because($e->getMessage(), $e);
        }
    }

    /**
     * @param array<array-key, mixed> $candidate
     */
    private function toPlace(array $candidate): ?Place
    {
        $name = JsonPath::stringAt($candidate, 'name');
        $latitude = JsonValue::asFloatOrNull($candidate['latitude'] ?? null);
        $longitude = JsonValue::asFloatOrNull($candidate['longitude'] ?? null);

        if (null === $name || null === $latitude || null === $longitude) {
            return null;
        }

        $country = JsonPath::stringAt($candidate, 'country');

        return new Place(null === $country ? $name : $name . ', ' . $country, $latitude, $longitude);
    }
}
