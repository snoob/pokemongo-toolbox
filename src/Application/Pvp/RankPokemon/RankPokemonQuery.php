<?php

declare(strict_types=1);

namespace App\Application\Pvp\RankPokemon;

use App\Domain\Pokemon\Exception\InvalidIv;
use App\Domain\Pokemon\Model\IvFloor;
use App\Domain\Pokemon\Model\IvPattern;
use App\Domain\Pokemon\Model\Species;
use App\Domain\Pvp\Model\Cp;
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
     * @param ?IvFloor     $floor     the lowest IVs the Pokémon can come with; null means
     *                                a wild catch, which can roll anything
     * @param ?Cp          $cp        the CP the game shows: it checks the IVs, and settles
     *                                those left unknown
     */
    public function __construct(
        public string $identifier,
        public ?IvPattern $iv = null,
        public array $leagues = [],
        public ?PokemonLevel $levelCap = null,
        public bool $shadow = false,
        public ?MegaLevel $megaLevel = null,
        public ?IvFloor $floor = null,
        public ?Cp $cp = null,
    ) {
        if (null === $iv) {
            return;
        }

        // IVs under the floor cannot be the Pokémon in hand: the source was misnamed.
        $lowest = $iv->lowestKnown();

        if (null !== $lowest && $lowest < $this->effectiveFloor()->minimum) {
            throw InvalidIv::belowFloor($iv, $this->effectiveFloor());
        }

        if (null === $cp && !$iv->isComplete()) {
            throw InvalidIv::unknownWithoutCp($iv);
        }
    }

    public function effectiveLevelCap(): PokemonLevel
    {
        return $this->levelCap ?? PokemonLevel::regularCap();
    }

    public function effectiveFloor(): IvFloor
    {
        return $this->floor ?? IvFloor::wild();
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
