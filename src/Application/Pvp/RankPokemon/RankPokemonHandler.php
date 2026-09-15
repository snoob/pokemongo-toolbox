<?php

declare(strict_types=1);

namespace App\Application\Pvp\RankPokemon;

use App\Domain\Pokemon\Model\Species;
use App\Domain\Pokemon\Port\SpeciesCatalog;
use App\Domain\Pokemon\Service\SpeciesResolver;
use App\Domain\Pvp\Exception\SpeciesIneligibleForLeague;
use App\Domain\Pvp\Model\League;
use App\Domain\Pvp\Model\MegaLevel;
use App\Domain\Pvp\Model\Moveset;
use App\Domain\Pvp\Model\PokemonLevel;
use App\Domain\Pvp\Model\PowerUpTarget;
use App\Domain\Pvp\Model\RankedSpread;
use App\Domain\Pvp\Port\MegaLevelTable;
use App\Domain\Pvp\Port\PvpRankingCatalog;
use App\Domain\Pvp\Service\CpCalculator;
use App\Domain\Pvp\Service\ExclusiveMoveFinder;
use App\Domain\Pvp\Service\IvRankCalculator;

final readonly class RankPokemonHandler
{
    public function __construct(
        private SpeciesResolver $resolver,
        private SpeciesCatalog $species,
        private PvpRankingCatalog $rankings,
        private IvRankCalculator $ivRanker,
        private CpCalculator $cp,
        private MegaLevelTable $megaLevels,
        private ExclusiveMoveFinder $exclusiveMoves,
    ) {}

    public function __invoke(RankPokemonQuery $query): RankPokemonResult
    {
        $species = $this->resolver->resolve(
            $query->identifier,
            $query->shadow,
            null !== $query->megaLevel,
            $query->megaVariant,
        );
        $levelCap = $query->effectiveLevelCap();
        $outcomes = [];

        foreach ($this->leaguesFor($query, $species) as $league) {
            $spread = null;
            $best = null;
            $ineligible = false;

            try {
                // Ranking every spread is what makes both answers available: where the
                // given IVs land, and which spread to hunt for in that league.
                $ranking = $this->ivRanker->rank($species->baseStats, $league, $levelCap);
                $best = $ranking->best();
                $spread = null === $query->iv ? null : $ranking->for($query->iv);
            } catch (SpeciesIneligibleForLeague) {
                $ineligible = true;
            }

            $outcomes[] = new LeagueOutcome(
                league: $league,
                speciesRank: $this->rankings->rankOf($species->id, $league),
                spread: $spread,
                best: $best,
                powerUpTo: $this->powerUpTo($species, $league, $spread, $query->megaLevel),
                bestPowerUpTo: $this->powerUpTo($species, $league, $best, $query->megaLevel),
                ineligible: $ineligible,
            );
        }

        return new RankPokemonResult(
            $species,
            $query->iv,
            $levelCap,
            $outcomes,
            $query->megaLevel,
            null === $query->megaLevel ? null : $this->megaLevels->effectsAt($query->megaLevel),
            $this->exclusiveMoves->in($this->movesetsIn($outcomes)),
        );
    }

    /**
     * @param list<LeagueOutcome> $outcomes
     *
     * @return list<Moveset>
     */
    private function movesetsIn(array $outcomes): array
    {
        $movesets = [];

        foreach ($outcomes as $outcome) {
            $moveset = $outcome->speciesRank?->moveset;

            if (null !== $moveset) {
                $movesets[] = $moveset;
            }
        }

        return $movesets;
    }

    /**
     * What the base form must read on screen before Mega Evolving.
     *
     * A Mega Edition caps the CP of the *mega* form, so the number the game shows while
     * powering up — the base form's CP — is lower and is the one to aim for.
     */
    private function powerUpTo(
        Species $species,
        League $league,
        ?RankedSpread $spread,
        ?MegaLevel $megaLevel,
    ): ?PowerUpTarget {
        // An uncapped league asks nothing of the CP, so there is no figure to aim for.
        if (null === $league->cpCap() || !$species->isMega() || null === $spread) {
            return null;
        }

        $base = $this->species->baseFormOf($species->dex);

        if (null === $base) {
            return null;
        }

        // At its last Mega Level the mega fights a couple of Pokémon levels above where
        // it actually sits, so the form to power up stops that much lower.
        $level = $this->beforeEvolving($spread->level, $megaLevel);
        $beforeEvolving = $this->cp->cpAt($base->baseStats, $spread->iv, $level);

        return new PowerUpTarget(
            $beforeEvolving,
            $level,
            // The gain is the step between the two figures on screen: what the game shows
            // while powering up, and what it shows once evolved. At the last Mega Level
            // that step also carries the two free levels, which is exactly what happens.
            (int) round((($spread->cp->value / $beforeEvolving->value) - 1) * 100),
        );
    }

    private function beforeEvolving(PokemonLevel $level, ?MegaLevel $megaLevel): PokemonLevel
    {
        $gained = null === $megaLevel ? 0 : $this->megaLevels->effectsAt($megaLevel)->additionalLevels;

        return new PokemonLevel(max(PokemonLevel::MIN, $level->value - $gained));
    }

    /**
     * @return non-empty-list<League>
     */
    private function leaguesFor(RankPokemonQuery $query, Species $species): array
    {
        if ([] !== $query->leagues) {
            return $query->leagues;
        }

        // A mega is barred from the standard leagues, so ranking it there would only
        // ever answer "unranked"; the Mega Editions are where it competes.
        return $species->isMega() ? League::megaEditions() : League::standard();
    }
}
