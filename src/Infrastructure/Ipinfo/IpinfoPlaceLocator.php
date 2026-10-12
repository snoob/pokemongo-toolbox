<?php

declare(strict_types=1);

namespace App\Infrastructure\Ipinfo;

use App\Domain\Weather\Exception\PlaceNotFound;
use App\Domain\Weather\Model\Place;
use App\Domain\Weather\Port\PlaceLocator;
use App\Infrastructure\Json\JsonFetcher;
use App\Infrastructure\Json\JsonFetchFailed;
use App\Infrastructure\Json\JsonPath;

/**
 * Guesses the player's town from the public IP address of this machine. No key needed;
 * the answer is as good as the network's geolocation — fine at home, far off behind a
 * VPN or a mobile carrier, hence the name it returns for the player to check.
 */
final readonly class IpinfoPlaceLocator implements PlaceLocator
{
    private const string URL = 'https://ipinfo.io/json';

    public function __construct(
        private JsonFetcher $fetcher,
    ) {}

    #[\Override]
    public function here(): Place
    {
        try {
            $answer = $this->fetcher->fetch(self::URL);
        } catch (JsonFetchFailed $e) {
            throw PlaceNotFound::fromNetwork($e->getMessage());
        }

        // "48.88,2.51": latitude then longitude in a single string.
        $coordinates = explode(',', JsonPath::stringAt($answer, 'loc') ?? '');

        if (2 !== \count($coordinates) || !is_numeric($coordinates[0]) || !is_numeric($coordinates[1])) {
            throw PlaceNotFound::fromNetwork('the IP lookup returned no coordinates');
        }

        $name = implode(', ', array_filter([
            JsonPath::stringAt($answer, 'city'),
            JsonPath::stringAt($answer, 'country'),
        ]));

        return new Place('' === $name ? 'your IP location' : $name, (float) $coordinates[0], (float) $coordinates[1]);
    }
}
