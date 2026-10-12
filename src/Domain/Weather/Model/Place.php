<?php

declare(strict_types=1);

namespace App\Domain\Weather\Model;

/**
 * Where to forecast. The name is for the player to check the guess; the coordinates are
 * what the weather actually depends on.
 */
final readonly class Place
{
    public function __construct(
        public string $name,
        public float $latitude,
        public float $longitude,
    ) {
        if ($latitude < -90.0 || $latitude > 90.0 || $longitude < -180.0 || $longitude > 180.0) {
            throw new \InvalidArgumentException(\sprintf(
                'Coordinates %s, %s are not on Earth.',
                $latitude,
                $longitude,
            ));
        }
    }
}
