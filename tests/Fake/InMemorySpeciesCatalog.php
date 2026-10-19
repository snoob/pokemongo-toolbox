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

        return $this->preferBaseForm($matches);
    }

    /**
     * Within two edits of a declared name, nearest only: enough to exercise the callers,
     * the length-based allowance being the real adapter's business.
     */
    #[\Override]
    public function nearest(string $identifier): array
    {
        $needle = mb_strtolower(trim($identifier));
        $distances = array_map(fn(Species $s): int => $this->distance($s, $needle), $this->species);
        $nearest = min([3, ...$distances]);

        return $this->preferBaseForm(array_values(array_filter(
            $this->species,
            static fn(int $i): bool => ($distances[$i] ?? 3) === $nearest && $nearest < 3,
            \ARRAY_FILTER_USE_KEY,
        )));
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
    public function find(SpeciesId $id): ?Species
    {
        foreach ($this->species as $species) {
            if ($species->id->equals($id)) {
                return $species;
            }
        }

        return null;
    }

    /**
     * Edits from the needle to the nearest declared name, capped at three: within two is
     * a typo, enough to exercise the callers — the length-based allowance being the real
     * adapter's business.
     */
    private function distance(Species $species, string $needle): int
    {
        return min([
            3,
            ...array_map(static fn(string $name): int => levenshtein(
                $needle,
                mb_strtolower($name),
            ), $this->names[$species->id->value] ?? []),
        ]);
    }

    /**
     * @param list<Species> $matches
     *
     * @return list<Species>
     */
    private function preferBaseForm(array $matches): array
    {
        $base = array_values(array_filter($matches, static fn(Species $s): bool => $s->isBaseForm()));

        return [] !== $base ? $base : $matches;
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
