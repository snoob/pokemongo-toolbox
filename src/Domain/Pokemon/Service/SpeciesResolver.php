<?php

declare(strict_types=1);

namespace App\Domain\Pokemon\Service;

use App\Domain\Pokemon\Exception\AmbiguousSpecies;
use App\Domain\Pokemon\Exception\SpeciesNotFound;
use App\Domain\Pokemon\Model\SpeciesMatch;
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

    /**
     * A name that matches nothing is taken for the one it most likely misspells, provided
     * a single species is that close; the match says so, for the answer to tell.
     */
    public function resolve(string $identifier): SpeciesMatch
    {
        $candidates = $this->catalog->search($identifier);
        $guessed = false;

        if ([] === $candidates) {
            $candidates = $this->catalog->nearest($identifier);
            $guessed = true;
        }

        if ([] === $candidates) {
            throw SpeciesNotFound::forIdentifier($identifier);
        }

        if (\count($candidates) > 1) {
            throw new AmbiguousSpecies($identifier, $candidates);
        }

        return new SpeciesMatch($candidates[0], $guessed);
    }

    /**
     * A shadow form is a distinct entry in the catalogue; fall back to the regular form
     * rather than inventing stats the ranking source does not have.
     */
    public function resolveShadow(string $identifier): SpeciesMatch
    {
        $match = $this->resolve($identifier);
        $shadow = $this->catalog->find($match->species->id->shadow());

        return null === $shadow ? $match : new SpeciesMatch($shadow, $match->guessed);
    }
}
