<?php

declare(strict_types=1);

namespace App\Application\Pvp\RankPokemon;

use App\Domain\Pokemon\Exception\AmbiguousSpecies;
use App\Domain\Pokemon\Exception\SpeciesNotFound;
use App\Domain\Pokemon\Model\Species;
use App\Domain\Pokemon\Port\SpeciesCatalog;
use App\Domain\Pvp\Exception\SpeciesIneligibleForLeague;
use App\Domain\Pvp\Port\PvpRankingCatalog;
use App\Domain\Pvp\Service\IvRankCalculator;

final readonly class RankPokemonHandler
{
    public function __construct(
        private SpeciesCatalog $species,
        private PvpRankingCatalog $rankings,
        private IvRankCalculator $ivRanker,
    ) {}

    public function __invoke(RankPokemonQuery $query): RankPokemonResult
    {
        $species = $this->resolve($query);
        $levelCap = $query->effectiveLevelCap();
        $outcomes = [];

        foreach ($query->leagues as $league) {
            $spread = null;
            $ineligible = false;

            if (null !== $query->iv) {
                try {
                    $spread = $this->ivRanker->rank($species->baseStats, $league, $levelCap)->for($query->iv);
                } catch (SpeciesIneligibleForLeague) {
                    $ineligible = true;
                }
            }

            $outcomes[] = new LeagueOutcome(
                league: $league,
                speciesRank: $this->rankings->rankOf($species->id, $league),
                spread: $spread,
                ineligible: $ineligible,
            );
        }

        return new RankPokemonResult($species, $query->iv, $levelCap, $outcomes);
    }

    private function resolve(RankPokemonQuery $query): Species
    {
        $candidates = $this->species->search($query->identifier);

        if ([] === $candidates) {
            throw SpeciesNotFound::forIdentifier($query->identifier);
        }

        if (\count($candidates) > 1) {
            throw new AmbiguousSpecies($query->identifier, $candidates);
        }

        $species = $candidates[0];

        if (!$query->shadow) {
            return $species;
        }

        // A shadow form is a distinct entry in the catalog; fall back to the regular
        // form rather than inventing stats the ranking source does not have.
        return $this->species->find($species->id->shadow()) ?? $species;
    }
}
