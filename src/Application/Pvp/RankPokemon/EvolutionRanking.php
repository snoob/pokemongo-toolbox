<?php

declare(strict_types=1);

namespace App\Application\Pvp\RankPokemon;

use App\Domain\Pokemon\Model\EvolutionPath;
use App\Domain\Pokemon\Model\Move;
use App\Domain\Pokemon\Model\Species;
use App\Domain\Pvp\Model\League;

/**
 * One form of the family, ranked league by league, with the path that leads to it from
 * the form that was asked for.
 */
final readonly class EvolutionRanking
{
    /**
     * @param non-empty-list<LeagueOutcome> $outcomes
     */
    public function __construct(
        public EvolutionPath $path,
        /** @var non-empty-list<LeagueOutcome> */
        public array $outcomes,
        public ?Move $exclusiveMove = null,
    ) {}

    public function species(): Species
    {
        return $this->path->last();
    }

    /**
     * Reading an outcome by league beats indexing the list: callers ask for what they
     * mean, and the order of the leagues stays an implementation detail.
     */
    public function outcomeFor(League $league): ?LeagueOutcome
    {
        foreach ($this->outcomes as $outcome) {
            if ($league === $outcome->league) {
                return $outcome;
            }
        }

        return null;
    }
}
