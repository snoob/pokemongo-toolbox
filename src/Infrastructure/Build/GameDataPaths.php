<?php

declare(strict_types=1);

namespace App\Infrastructure\Build;

/**
 * Where `app:data:build` writes what it generates.
 */
final readonly class GameDataPaths
{
    public function __construct(
        public string $species,
        public string $cpMultipliers,
        public string $megaLevels,
        public string $moves,
        public string $weather,
        public string $translations,
    ) {}
}
