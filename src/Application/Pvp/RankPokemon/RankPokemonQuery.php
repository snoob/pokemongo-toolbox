<?php

declare(strict_types=1);

namespace App\Application\Pvp\RankPokemon;

use App\Domain\Pokemon\Model\IvSpread;
use App\Domain\Pokemon\Model\Species;
use App\Domain\Pvp\Model\League;
use App\Domain\Pvp\Model\MegaLevel;
use App\Domain\Pvp\Model\PokemonLevel;

final readonly class RankPokemonQuery
{
    /**
     * @param list<League> $leagues   an empty list lets each form decide: a mega is only
     *                                rankable in the Mega Editions, anything else in the
     *                                standard leagues
     * @param ?MegaLevel   $megaLevel the level every mega of the family is ranked at;
     *                                null means the one a PvP player targets
     */
    public function __construct(
        public string $identifier,
        public ?IvSpread $iv = null,
        public array $leagues = [],
        public ?PokemonLevel $levelCap = null,
        public bool $shadow = false,
        public ?MegaLevel $megaLevel = null,
    ) {}

    public function effectiveLevelCap(): PokemonLevel
    {
        return $this->levelCap ?? PokemonLevel::regularCap();
    }

    public function effectiveMegaLevel(): MegaLevel
    {
        return $this->megaLevel ?? MegaLevel::default();
    }

    /**
     * A mega is barred from the standard leagues, so it only gets the Mega Editions —
     * "--league=great" ranks the family without its megas. The rest of the family may
     * enter any league, Mega Editions included, but is shown in the standard ones unless
     * asked otherwise.
     *
     * @return list<League>
     */
    public function leaguesFor(Species $species): array
    {
        if ([] === $this->leagues) {
            return $species->isMega() ? League::megaEditions() : League::standard();
        }

        return array_values(array_filter(
            $this->leagues,
            static fn(League $league): bool => !$species->isMega() || $league->allowsMega(),
        ));
    }
}
