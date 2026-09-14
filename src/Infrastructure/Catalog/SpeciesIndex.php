<?php

declare(strict_types=1);

namespace App\Infrastructure\Catalog;

use App\Domain\Pokemon\Model\Species;
use App\Domain\Pokemon\Model\SpeciesId;
use App\Infrastructure\Naming\LocalisedSpeciesNames;

/**
 * Lookup tables over a species list: by id, by normalised name in every shipped locale,
 * and by Pokédex number.
 */
final readonly class SpeciesIndex
{
    /** @var array<string, Species> */
    private array $byId;

    /** @var array<string, list<string>> */
    private array $byName;

    /** @var array<int, list<string>> */
    private array $byDex;

    /**
     * @param list<Species> $species
     */
    public function __construct(array $species, NameNormalizer $normalizer, LocalisedSpeciesNames $names)
    {
        $byId = [];
        $byName = [];
        $byDex = [];

        foreach ($species as $entry) {
            $byId[$entry->id->value] = $entry;
            $byDex[$entry->dex->value][] = $entry->id->value;

            foreach ($names->of($entry) as $name) {
                $key = $normalizer->normalize($name);

                if ('' !== $key && !\in_array($entry->id->value, $byName[$key] ?? [], true)) {
                    $byName[$key][] = $entry->id->value;
                }
            }
        }

        $this->byId = $byId;
        $this->byName = $byName;
        $this->byDex = $byDex;
    }

    public function byId(string $id): ?Species
    {
        return $this->byId[$id] ?? null;
    }

    public function byIdentity(SpeciesId $id): ?Species
    {
        return $this->byId($id->value);
    }

    /** @return list<Species> */
    public function byDex(int $dex): array
    {
        return $this->hydrate($this->byDex[$dex] ?? []);
    }

    /** @return list<Species> */
    public function byExactName(string $normalized): array
    {
        return $this->hydrate($this->byName[$normalized] ?? []);
    }

    /** @return list<Species> */
    public function byNamePrefix(string $normalized): array
    {
        $ids = [];

        foreach ($this->byName as $name => $candidates) {
            if (!str_starts_with($name, $normalized)) {
                continue;
            }

            $ids = [...$ids, ...$candidates];
        }

        return $this->hydrate(array_values(array_unique($ids)));
    }

    /**
     * @param list<string> $ids
     *
     * @return list<Species>
     */
    private function hydrate(array $ids): array
    {
        $species = [];

        foreach ($ids as $id) {
            $found = $this->byId($id);

            if (null !== $found) {
                $species[] = $found;
            }
        }

        return $species;
    }
}
