<?php

declare(strict_types=1);

namespace App\Application\Pvp\RankPokemon;

use App\Domain\Pokemon\Model\EvolutionPath;
use App\Domain\Pokemon\Service\EvolutionTree;
use App\Domain\Pokemon\Service\SpeciesResolver;
use App\Domain\Pvp\Model\League;
use App\Domain\Pvp\Port\MegaLevelTable;

final readonly class RankPokemonHandler
{
    public function __construct(
        private SpeciesResolver $resolver,
        private EvolutionTree $evolutions,
        private EvolutionRanker $ranker,
        private MegaLevelTable $megaLevels,
    ) {}

    public function __invoke(RankPokemonQuery $query): RankPokemonResult
    {
        $asked = $query->shadow
            ? $this->resolver->resolveShadow($query->identifier)
            : $this->resolver->resolve($query->identifier);
        $rankings = [];

        // Evolving keeps the IVs and the level, so the form in hand is worth ranking as
        // everything it can become — megas included.
        $paths = $this->evolutions->from($asked);

        foreach ($paths as $path) {
            $leagues = $query->leaguesFor($path->last());

            if ([] === $leagues || $this->isUnrankedOnTheWay($path, $paths, $leagues)) {
                continue;
            }

            $rankings[] = $this->rank($path, $leagues, $query);
        }

        // A mega asked by its id and sent to a standard league still deserves an answer,
        // even if it is "unranked".
        if ([] === $rankings) {
            $leagues = [] === $query->leagues ? League::standard() : $query->leagues;
            $rankings[] = $this->rank(new EvolutionPath([$asked]), $leagues, $query);
        }

        $megaLevel = $this->ranksAMega($rankings) ? $query->effectiveMegaLevel() : null;

        return new RankPokemonResult(
            $asked,
            $query->iv,
            $query->effectiveLevelCap(),
            $rankings,
            $megaLevel,
            null === $megaLevel ? null : $this->megaLevels->effectsAt($megaLevel),
        );
    }

    /**
     * @param non-empty-list<League> $leagues
     */
    private function rank(EvolutionPath $path, array $leagues, RankPokemonQuery $query): EvolutionRanking
    {
        return $this->ranker->rank(
            $path,
            $leagues,
            $query->iv,
            $query->effectiveLevelCap(),
            $query->effectiveMegaLevel(),
        );
    }

    /**
     * A form that still evolves (Ralts, Kirlia) and that no league ranks would only fill
     * a table with "unranked" — the form in hand included, since the power-up column of
     * the next tables already answers for it. Final forms stay whatever their rank: they
     * are the answer, and a final form in hand would leave nothing to show. A mega does
     * not count as a further evolution — Gardevoir is final even though it Mega Evolves.
     *
     * @param list<EvolutionPath>    $paths
     * @param non-empty-list<League> $leagues
     */
    private function isUnrankedOnTheWay(EvolutionPath $path, array $paths, array $leagues): bool
    {
        if ($this->ranker->isRanked($path->last(), $leagues)) {
            return false;
        }

        foreach ($paths as $other) {
            if ($path->leadsTo($other) && !$other->last()->isMega()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<EvolutionRanking> $rankings
     */
    private function ranksAMega(array $rankings): bool
    {
        foreach ($rankings as $ranking) {
            if ($ranking->species()->isMega()) {
                return true;
            }
        }

        return false;
    }
}
