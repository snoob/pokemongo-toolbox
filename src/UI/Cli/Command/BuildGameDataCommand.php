<?php

declare(strict_types=1);

namespace App\UI\Cli\Command;

use App\Infrastructure\Build\CpMultiplierTableBuilder;
use App\Infrastructure\Build\SpeciesCatalogBuilder;
use App\Infrastructure\Build\SpeciesNameCatalogueBuilder;
use App\Infrastructure\Naming\SpeciesNameResolver;
use App\Infrastructure\Translation\XliffCatalogueWriter;
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
        private readonly SpeciesNameCatalogueBuilder $nameBuilder,
        private readonly XliffCatalogueWriter $xliff,
        private readonly string $speciesFile,
        private readonly string $cpMultiplierFile,
        private readonly string $translationsDir,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->section('Espèces');
        $built = $this->speciesBuilder->build();
        $this->writeJson($this->speciesFile, $built['species']);
        $io->success(\sprintf('%d formes écrites dans %s', \count($built['species']['species']), $this->speciesFile));

        $io->section('Multiplicateurs de CP');
        $multipliers = $this->cpMultiplierBuilder->build();
        $this->writeJson($this->cpMultiplierFile, $multipliers);
        $io->success(\sprintf(
            '%d niveaux écrits dans %s',
            \count($multipliers['multipliers']),
            $this->cpMultiplierFile,
        ));

        $io->section('Catalogues de traduction');

        foreach ($this->nameBuilder->build() as $locale => $names) {
            $this->xliff->write($this->translationsDir, SpeciesNameResolver::SPECIES_DOMAIN, $locale, $names);
            $io->success(\sprintf('%d noms écrits pour la locale "%s"', \count($names), $locale));
        }

        // Only the English form labels are generated: the French ones are curated by
        // hand in pokemon_form.fr.xlf, since "Shadow" has no Pokédex translation.
        $this->xliff->write($this->translationsDir, SpeciesNameResolver::FORM_DOMAIN, 'en', $built['formLabels']);
        $io->success(\sprintf('%d libellés de forme écrits pour la locale "en"', \count($built['formLabels'])));

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
