<?php

declare(strict_types=1);

namespace App\Application\Pvp\RankPokemon;

use App\Domain\Pokemon\Model\IvSpread;
use App\Domain\Pokemon\Model\Species;
use App\Domain\Pvp\Model\MegaLevel;
use App\Domain\Pvp\Model\MegaLevelEffects;
use App\Domain\Pvp\Model\PokemonLevel;

/**
 * The asked form and everything it can become, each ranked on its own. The Mega Level
 * is only set when a mega is among them.
 */
final readonly class RankPokemonResult
{
    /**
     * @param non-empty-list<EvolutionRanking> $evolutions the asked form first
     */
    public function __construct(
        public Species $asked,
        public ?IvSpread $iv,
        public PokemonLevel $levelCap,
        /** @var non-empty-list<EvolutionRanking> */
        public array $evolutions,
        public ?MegaLevel $megaLevel = null,
        public ?MegaLevelEffects $megaLevelEffects = null,
    ) {}

    public function rankingOf(string $speciesId): ?EvolutionRanking
    {
        foreach ($this->evolutions as $ranking) {
            if ($speciesId === $ranking->species()->id->value) {
                return $ranking;
            }
        }

        return null;
    }
}
