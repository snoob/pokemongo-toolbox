<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use App\Domain\Pokemon\Model\DexNumber;
use App\Domain\Pokemon\Model\Species;
use App\Domain\Pokemon\Model\SpeciesId;
use App\Domain\Pokemon\Port\SpeciesCatalog;

/**
 * Real behaviour, no storage: matches on id, Pokédex number, or any name the caller
 * declares for a form. Names live beside the species, as they do in production, where
 * they come from the translation catalogues rather than the model.
 */
final readonly class InMemorySpeciesCatalog implements SpeciesCatalog
{
    /**
     * @param list<Species>               $species
     * @param array<string, list<string>> $names species id => names it answers to
     */
    public function __construct(
        private array $species,
        private array $names = [],
    ) {}

    #[\Override]
    public function search(string $identifier): array
    {
        $needle = mb_strtolower(trim($identifier));
        $matches = [];

        foreach ($this->species as $species) {
            if (!$this->matches($species, $needle)) {
                continue;
            }

            $matches[] = $species;
        }

        $base = array_values(array_filter($matches, static fn(Species $s): bool => $s->isBaseForm()));

        return [] !== $base ? $base : $matches;
    }

    #[\Override]
    public function megaFormsOf(DexNumber $dex): array
    {
        return array_values(array_filter(
            $this->species,
            static fn(Species $s): bool => $s->dex->value === $dex->value && $s->isMega(),
        ));
    }

    #[\Override]
    public function baseFormOf(DexNumber $dex): ?Species
    {
        foreach ($this->species as $species) {
            if ($species->dex->value === $dex->value && $species->isBaseForm()) {
                return $species;
            }
        }

        return null;
    }

    #[\Override]
    public function find(SpeciesId $id): ?Species
    {
        foreach ($this->species as $species) {
            if ($species->id->equals($id)) {
                return $species;
            }
        }

        return null;
    }

    private function matches(Species $species, string $needle): bool
    {
        if (mb_strtolower($species->id->value) === $needle || (string) $species->dex->value === $needle) {
            return true;
        }

        foreach ($this->names[$species->id->value] ?? [] as $name) {
            if (mb_strtolower($name) === $needle) {
                return true;
            }
        }

        return false;
    }
}
