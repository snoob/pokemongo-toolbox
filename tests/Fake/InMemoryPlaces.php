<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use App\Domain\Weather\Exception\PlaceNotFound;
use App\Domain\Weather\Model\Place;
use App\Domain\Weather\Port\PlaceLocator;
use App\Domain\Weather\Port\PlaceSearch;

/**
 * Both ways of finding a place: the guess, and the places a player can type.
 */
final readonly class InMemoryPlaces implements PlaceLocator, PlaceSearch
{
    /**
     * @param array<string, Place> $named query => place, matched case-insensitively
     */
    public function __construct(
        private ?Place $here = null,
        private array $named = [],
    ) {}

    #[\Override]
    public function here(): Place
    {
        return $this->here ?? throw PlaceNotFound::fromNetwork('offline');
    }

    #[\Override]
    public function find(string $query): Place
    {
        foreach ($this->named as $name => $place) {
            if (mb_strtolower($name) === mb_strtolower(trim($query))) {
                return $place;
            }
        }

        throw PlaceNotFound::forQuery($query);
    }
}
