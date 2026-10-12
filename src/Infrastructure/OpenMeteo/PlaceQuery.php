<?php

declare(strict_types=1);

namespace App\Infrastructure\OpenMeteo;

use App\Infrastructure\Json\JsonPath;

/**
 * "Paris, United States" split for a geocoder that searches by name only: the town goes
 * to the geocoder, the country picks among the namesakes it returns.
 */
final readonly class PlaceQuery
{
    public string $name;
    public ?string $country;

    public function __construct(string $query)
    {
        $parts = array_map(trim(...), explode(',', $query, 2));
        $this->name = $parts[0];
        $country = $parts[1] ?? '';
        $this->country = '' === $country ? null : $country;
    }

    /**
     * Whether a geocoder result lies in the country asked for — by name or by ISO code,
     * whichever the player typed. Without a country, any result does.
     *
     * @param array<array-key, mixed> $candidate
     */
    public function accepts(array $candidate): bool
    {
        if (null === $this->country) {
            return true;
        }

        $wanted = mb_strtolower($this->country);

        return \in_array(
            $wanted,
            [
                mb_strtolower(JsonPath::stringAt($candidate, 'country') ?? ''),
                mb_strtolower(JsonPath::stringAt($candidate, 'country_code') ?? ''),
            ],
            true,
        );
    }
}
