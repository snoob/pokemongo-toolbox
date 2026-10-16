<?php

declare(strict_types=1);

namespace App\Domain\Pokemon\Service;

use App\Domain\Pokemon\Exception\AmbiguousSpecies;
use App\Domain\Pokemon\Exception\SpeciesNotFound;
use App\Domain\Pokemon\Model\Species;
use App\Domain\Pokemon\Port\SpeciesCatalog;

/**
 * Turns what the user asked for — a name, a Pokédex number, plus whether they hold a
 * shadow — into exactly one species, or says why it cannot.
 *
 * Megas are not resolved here: they are reached as an evolution of the form in hand.
 */
final readonly class SpeciesResolver
{
    public function __construct(
        private SpeciesCatalog $catalog,
    ) {}

    public function resolve(string $identifier): Species
    {
        $candidates = $this->catalog->search($identifier);

        if ([] === $candidates) {
            throw SpeciesNotFound::forIdentifier($identifier);
        }

        if (\count($candidates) > 1) {
            throw new AmbiguousSpecies($identifier, $candidates);
        }

        return $candidates[0];
    }

    /**
     * A shadow form is a distinct entry in the catalogue; fall back to the regular form
     * rather than inventing stats the ranking source does not have.
     */
    public function resolveShadow(string $identifier): Species
    {
        $species = $this->resolve($identifier);

        return $this->catalog->find($species->id->shadow()) ?? $species;
    }
}
