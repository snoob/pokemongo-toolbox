<?php

declare(strict_types=1);

namespace App\Application\Pvp\RankPokemon;

use App\Domain\Pokemon\Model\IvSpread;
use App\Domain\Pokemon\Model\Species;
use App\Domain\Pvp\Model\League;
use App\Domain\Pvp\Model\PokemonLevel;

final readonly class RankPokemonResult
{
    /**
     * @param non-empty-list<LeagueOutcome> $outcomes
     */
    public function __construct(
        public Species $species,
        public ?IvSpread $iv,
        public PokemonLevel $levelCap,
        /** @var non-empty-list<LeagueOutcome> */
        public array $outcomes,
    ) {}

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
