<?php

declare(strict_types=1);

namespace App\Application\Pvp\RankPokemon;

use App\Domain\Pokemon\Model\IvSpread;
use App\Domain\Pvp\Model\League;
use App\Domain\Pvp\Model\MegaLevel;
use App\Domain\Pvp\Model\PokemonLevel;

final readonly class RankPokemonQuery
{
    /**
     * @param list<League> $leagues an empty list lets the species decide: a mega is only
     *                              rankable in the Mega Editions, anything else in the
     *                              standard leagues
     */
    public function __construct(
        public string $identifier,
        public ?IvSpread $iv = null,
        public array $leagues = [],
        public ?PokemonLevel $levelCap = null,
        public bool $shadow = false,
        public ?MegaLevel $megaLevel = null,
        public ?string $megaVariant = null,
    ) {}

    public function effectiveLevelCap(): PokemonLevel
    {
        return $this->levelCap ?? PokemonLevel::regularCap();
    }
}
