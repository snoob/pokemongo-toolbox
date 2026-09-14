<?php

declare(strict_types=1);

namespace App\Application\Pvp\RankPokemon;

use App\Domain\Pokemon\Model\IvSpread;
use App\Domain\Pvp\Model\League;
use App\Domain\Pvp\Model\PokemonLevel;

final readonly class RankPokemonQuery
{
    /**
     * @param non-empty-list<League> $leagues
     */
    public function __construct(
        public string $identifier,
        public ?IvSpread $iv = null,
        public array $leagues = [League::Great, League::Ultra, League::Master],
        public ?PokemonLevel $levelCap = null,
        public bool $shadow = false,
    ) {}

    public function effectiveLevelCap(): PokemonLevel
    {
        return $this->levelCap ?? PokemonLevel::regularCap();
    }
}
