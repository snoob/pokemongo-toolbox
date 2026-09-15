<?php

declare(strict_types=1);

namespace App\Domain\Pokemon\Service;

use App\Domain\Pokemon\Exception\AmbiguousSpecies;
use App\Domain\Pokemon\Exception\SpeciesNotFound;
use App\Domain\Pokemon\Model\Species;
use App\Domain\Pokemon\Port\SpeciesCatalog;

/**
 * Turns what the user asked for — a name, a Pokédex number, plus the form they want —
 * into exactly one species, or says why it cannot.
 */
final readonly class SpeciesResolver
{
    public function __construct(
        private SpeciesCatalog $catalog,
    ) {}

    public function resolve(
        string $identifier,
        bool $shadow = false,
        bool $mega = false,
        ?string $megaVariant = null,
    ): Species {
        $candidates = $this->catalog->search($identifier);

        if ([] === $candidates) {
            throw SpeciesNotFound::forIdentifier($identifier);
        }

        if (\count($candidates) > 1) {
            throw new AmbiguousSpecies($identifier, $candidates);
        }

        $species = $candidates[0];

        return match (true) {
            $mega => $this->megaFormOf($species, $identifier, $megaVariant),
            $shadow => $this->shadowFormOf($species),
            default => $species,
        };
    }

    /**
     * Charizard, Raichu and Mewtwo each have two megas, so the flag cannot pick for the
     * user there; every other species has at most one.
     */
    private function megaFormOf(Species $species, string $identifier, ?string $variant): Species
    {
        $megas = $this->catalog->megaFormsOf($species->dex);

        if (null !== $variant) {
            $megas = array_values(array_filter($megas, static fn(Species $mega): bool => $mega->hasMegaVariant(
                $variant,
            )));
        }

        if ([] === $megas) {
            throw SpeciesNotFound::withoutMegaForm($identifier);
        }

        if (\count($megas) > 1) {
            throw new AmbiguousSpecies($identifier, $megas);
        }

        return $megas[0];
    }

    /**
     * A shadow form is a distinct entry in the catalogue; fall back to the regular form
     * rather than inventing stats the ranking source does not have.
     */
    private function shadowFormOf(Species $species): Species
    {
        return $this->catalog->find($species->id->shadow()) ?? $species;
    }
}
