<?php

declare(strict_types=1);

namespace App\UI\Cli\Command;

use App\Infrastructure\Build\CpMultiplierTableBuilder;
use App\Infrastructure\Build\GameDataPaths;
use App\Infrastructure\Build\MegaLevelTableBuilder;
use App\Infrastructure\Build\MoveNameCatalogueBuilder;
use App\Infrastructure\Build\SpeciesCatalogBuilder;
use App\Infrastructure\Build\SpeciesNameCatalogueBuilder;
use App\Infrastructure\Naming\MoveNameResolver;
use App\Infrastructure\Naming\SpeciesNameResolver;
use App\Infrastructure\Translation\XliffCatalogueWriter;
use App\PharEnvironment;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'pogo:data:build',
    description: 'Regenerate the committed game data and translation catalogues from upstream sources',
)]
final class BuildGameDataCommand extends Command
{
    public function __construct(
        private readonly SpeciesCatalogBuilder $speciesBuilder,
        private readonly CpMultiplierTableBuilder $cpMultiplierBuilder,
        private readonly MegaLevelTableBuilder $megaLevelBuilder,
        private readonly SpeciesNameCatalogueBuilder $nameBuilder,
        private readonly MoveNameCatalogueBuilder $moveNameBuilder,
        private readonly XliffCatalogueWriter $xliff,
        private readonly GameDataPaths $paths,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // This command writes back into the repository; inside a read-only archive there
        // is nothing for it to update.
        if (PharEnvironment::isRunning()) {
            $io->error('pogo:data:build only runs from a checkout — the PHAR ships the data already built.');

            return Command::FAILURE;
        }

        $io->section('Species');
        $built = $this->speciesBuilder->build();
        $this->writeJson($this->paths->species, $built['species']);
        $io->success(\sprintf('%d forms written to %s', \count($built['species']['species']), $this->paths->species));

        $io->section('CP multipliers');
        $multipliers = $this->cpMultiplierBuilder->build();
        $this->writeJson($this->paths->cpMultipliers, $multipliers);
        $io->success(\sprintf(
            '%d levels written to %s',
            \count($multipliers['multipliers']),
            $this->paths->cpMultipliers,
        ));

        $io->section('Moves');
        $this->writeJson($this->paths->moves, ['generatedAt' => date(\DATE_ATOM), 'moves' => $built['moves']]);
        $io->success(\sprintf('%d moves written to %s', \count($built['moves']), $this->paths->moves));

        $io->section('Mega levels');
        $megaLevels = $this->megaLevelBuilder->build();
        $this->writeJson($this->paths->megaLevels, $megaLevels);
        $io->success(\sprintf(
            '%d species with a mega progression written to %s',
            \count($megaLevels['maxLevelByDex']),
            $this->paths->megaLevels,
        ));

        $io->section('Translation catalogues');

        foreach ($this->nameBuilder->build() as $locale => $names) {
            $this->xliff->write($this->paths->translations, SpeciesNameResolver::SPECIES_DOMAIN, $locale, $names);
            $io->success(\sprintf('%d names written for locale "%s"', \count($names), $locale));
        }

        // Only the English form labels are generated: the French ones are curated by
        // hand in pokemon_form.fr.xlf, since "Shadow" has no Pokédex translation.
        $this->xliff->write($this->paths->translations, SpeciesNameResolver::FORM_DOMAIN, 'en', $built['formLabels']);
        $io->success(\sprintf('%d form labels written for locale "en"', \count($built['formLabels'])));

        foreach ($this->moveNameBuilder->build($built['moves']) as $locale => $names) {
            $this->xliff->write($this->paths->translations, MoveNameResolver::DOMAIN, $locale, $names);
            $io->success(\sprintf('%d move names written for locale "%s"', \count($names), $locale));
        }

        return Command::SUCCESS;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function writeJson(string $file, array $payload): void
    {
        $directory = \dirname($file);

        if (!is_dir($directory) && !mkdir($directory, 0o775, true) && !is_dir($directory)) {
            throw new \RuntimeException(\sprintf('Cannot create "%s".', $directory));
        }

        file_put_contents(
            $file,
            json_encode(
                $payload,
                \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR,
            )
                . "\n",
        );
    }
}
