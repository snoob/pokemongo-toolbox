<?php

declare(strict_types=1);

namespace App\Infrastructure\Build;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Where `app:data:build` writes what it generates.
 *
 * Wired by attribute rather than in services.yaml: the PHAR leaves this class out, and a
 * definition in YAML would then name a class the container cannot find (decision 100).
 */
final readonly class GameDataPaths
{
    public function __construct(
        #[Autowire('%pogo.data_dir%/species.json')]
        public string $species,
        #[Autowire('%pogo.data_dir%/cpm.json')]
        public string $cpMultipliers,
        #[Autowire('%pogo.data_dir%/mega-levels.json')]
        public string $megaLevels,
        #[Autowire('%pogo.data_dir%/moves.json')]
        public string $moves,
        #[Autowire('%pogo.data_dir%/weather.json')]
        public string $weather,
        #[Autowire('%kernel.project_dir%/translations')]
        public string $translations,
    ) {}
}
