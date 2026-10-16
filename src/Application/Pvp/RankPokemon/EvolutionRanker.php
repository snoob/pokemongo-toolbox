<?php

declare(strict_types=1);

namespace App\Application\Pvp\RankPokemon;

use App\Domain\Pokemon\Model\EvolutionPath;
use App\Domain\Pokemon\Model\IvSpread;
use App\Domain\Pokemon\Model\Species;
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

/**
 * Ranks one form of the family league by league, and says how far to power up the form
 * in hand before evolving it there. Kept apart from the handler, which walks the family.
 */
final readonly class EvolutionRanker
{
    public function __construct(
        private PvpRankingCatalog $rankings,
        private IvRankCalculator $ivRanker,
        private CpCalculator $cp,
        private MegaLevelTable $megaLevels,
        private ExclusiveMoveFinder $exclusiveMoves,
    ) {}

    /**
     * @param non-empty-list<League> $leagues
     */
    public function rank(
        EvolutionPath $path,
        array $leagues,
        ?IvSpread $iv,
        PokemonLevel $levelCap,
        MegaLevel $megaLevel,
    ): EvolutionRanking {
        $species = $path->last();
        $outcomes = [];

        foreach ($leagues as $league) {
            $spread = null;
            $best = null;
            $ineligible = false;

            try {
                // Ranking every spread is what makes both answers available: where the
                // given IVs land, and which spread to hunt for in that league.
                $ranking = $this->ivRanker->rank($species->baseStats, $league, $levelCap);
                $best = $ranking->best();
                $spread = null === $iv ? null : $ranking->for($iv);
            } catch (SpeciesIneligibleForLeague) {
                $ineligible = true;
            }

            $outcomes[] = new LeagueOutcome(
                league: $league,
                speciesRank: $this->rankings->rankOf($species->id, $league),
                spread: $spread,
                best: $best,
                powerUpTo: $this->powerUpTo($path, $league, $spread, $megaLevel),
                bestPowerUpTo: $this->powerUpTo($path, $league, $best, $megaLevel),
                ineligible: $ineligible,
            );
        }

        return new EvolutionRanking($path, $outcomes, $this->exclusiveMoves->in($this->movesetsIn($outcomes)));
    }

    /**
     * Whether any of the leagues ranks the species — a cheap lookup, unlike ranking its
     * 4096 spreads.
     *
     * @param non-empty-list<League> $leagues
     */
    public function isRanked(Species $species, array $leagues): bool
    {
        foreach ($leagues as $league) {
            if (null !== $this->rankings->rankOf($species->id, $league)) {
                return true;
            }
        }

        return false;
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
     * What the form in hand must read on screen before evolving.
     *
     * A league caps the CP of the form that fights. Evolving and Mega Evolving both keep
     * the level, so the number the game shows while powering up — the CP of the form in
     * hand at that level — is lower, and is the one to aim for.
     */
    private function powerUpTo(
        EvolutionPath $path,
        League $league,
        ?RankedSpread $spread,
        MegaLevel $megaLevel,
    ): ?PowerUpTarget {
        // An uncapped league asks nothing of the CP, so there is no figure to aim for.
        if (null === $league->cpCap() || !$path->isEvolution() || null === $spread) {
            return null;
        }

        // At its last Mega Level the mega fights a couple of Pokémon levels above where
        // it actually sits, so the form to power up stops that much lower.
        $level = $path->last()->isMega() ? $this->beforeMegaEvolving($spread->level, $megaLevel) : $spread->level;
        $beforeEvolving = $this->cp->cpAt($path->first()->baseStats, $spread->iv, $level);

        return new PowerUpTarget(
            $beforeEvolving,
            $level,
            // The gain is the step between the two figures on screen: what the game shows
            // while powering up, and what it shows once evolved. At the last Mega Level
            // that step also carries the two free levels, which is exactly what happens.
            $path->isMegaEvolution() ? (int) round((($spread->cp->value / $beforeEvolving->value) - 1) * 100) : null,
        );
    }

    private function beforeMegaEvolving(PokemonLevel $level, MegaLevel $megaLevel): PokemonLevel
    {
        $gained = $this->megaLevels->effectsAt($megaLevel)->additionalLevels;

        return new PokemonLevel(max(PokemonLevel::MIN, $level->value - $gained));
    }
}
