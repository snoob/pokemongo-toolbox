<?php

declare(strict_types=1);

namespace App\UI\Cli\Presenter;

use App\Application\Pvp\RankPokemon\LeagueOutcome;
use App\Application\Pvp\RankPokemon\RankPokemonResult;
use App\Domain\Pokemon\Model\Species;
use App\Domain\Pvp\Model\Moveset;
use App\Domain\Pvp\Model\PowerUpTarget;
use App\Domain\Pvp\Model\RankedSpread;
use App\Infrastructure\Naming\SpeciesNameResolver;

final readonly class PvpRankJsonPresenter
{
    public function __construct(
        private SpeciesNameResolver $names,
    ) {}

    public function present(RankPokemonResult $result, ?string $locale = null): string
    {
        $leagues = [];

        foreach ($result->outcomes as $outcome) {
            $leagues[$outcome->league->value] = $this->league($outcome, $result->species);
        }

        return json_encode(
            [
                'species' => [
                    'id' => $result->species->id->value,
                    'dex' => $result->species->dex->value,
                    'name' => $this->names->name($result->species, $locale),
                    'forms' => $result->species->forms,
                    'mega' => $result->species->isMega(),
                    'superMega' => $result->species->superMega,
                ],
                'iv' => null === $result->iv ? null : (string) $result->iv,
                'levelCap' => $result->levelCap->value,
                'leagues' => $leagues,
            ],
            \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function league(LeagueOutcome $outcome, Species $species): array
    {
        $rank = $outcome->speciesRank;
        $spread = $outcome->spread;

        return [
            'speciesRank' => null === $rank
                ? null
                : [
                    'position' => $rank->position,
                    'total' => $rank->total,
                    'score' => $rank->score,
                    'moveset' => $this->moveset($rank->moveset, $species),
                ],
            'ineligible' => $outcome->ineligible,
            'ivRank' => $this->spread($spread),
            'bestIv' => $this->spread($outcome->best),
            'powerUpTo' => $this->target($outcome->powerUpTo),
            'bestPowerUpTo' => $this->target($outcome->bestPowerUpTo),
        ];
    }

    /**
     * @return array{iv: string, position: int, total: int, percentOfBest: float, statProduct: float, cp: int, level: float}|null
     */
    private function spread(?RankedSpread $spread): ?array
    {
        return (
            null === $spread
                ? null
                : [
                    'iv' => (string) $spread->iv,
                    'position' => $spread->position,
                    'total' => $spread->total,
                    'percentOfBest' => round($spread->percentOfBest, 4),
                    'statProduct' => round($spread->statProduct, 4),
                    'cp' => $spread->cp->value,
                    'level' => $spread->level->value,
                ]
        );
    }

    /**
     * Each move carries whether it costs an Elite TM on this form: the machine reader
     * should not have to cross-reference a second list to find out.
     *
     * @return list<array{id: string, elite: bool}>|null
     */
    private function moveset(?Moveset $moveset, Species $species): ?array
    {
        if (null === $moveset) {
            return null;
        }

        $moves = [];

        foreach ($moveset->all() as $move) {
            $moves[] = ['id' => $move->value, 'elite' => $species->isElite($move)];
        }

        return $moves;
    }

    /**
     * @return array{cp: int, level: float}|null
     */
    private function target(?PowerUpTarget $target): ?array
    {
        return null === $target ? null : ['cp' => $target->cp->value, 'level' => $target->level->value];
    }
}
