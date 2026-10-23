<?php

declare(strict_types=1);

namespace App\UI\Cli\Presenter;

use App\Application\Pvp\RankPokemon\EvolutionRanking;
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
        return json_encode(
            [
                'species' => $this->species($result->asked, $locale),
                'guessedFrom' => $result->guessedFrom,
                'iv' => null === $result->iv ? null : (string) $result->iv,
                'ivFloor' => ['name' => $result->floor->name, 'minimum' => $result->floor->minimum],
                'levelCap' => $result->levelCap->value,
                'megaLevel' => $result->megaLevel?->value,
                'evolutions' => array_map(
                    /** @return array<string, mixed> */
                    fn(EvolutionRanking $ranking): array => $this->evolution($ranking, $locale),
                    $result->evolutions,
                ),
            ],
            \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR,
        );
    }

    /**
     * @return array{species: array<string, mixed>, path: list<string>, leagues: array<string, array<string, mixed>>}
     */
    private function evolution(EvolutionRanking $ranking, ?string $locale): array
    {
        $leagues = [];

        foreach ($ranking->outcomes as $outcome) {
            $leagues[$outcome->league->value] = $this->league($outcome, $ranking->species());
        }

        return [
            'species' => $this->species($ranking->species(), $locale),
            'path' => array_map(static fn(Species $stage): string => $stage->id->value, $ranking->path->stages),
            'leagues' => $leagues,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function species(Species $species, ?string $locale): array
    {
        return [
            'id' => $species->id->value,
            'dex' => $species->dex->value,
            'name' => $this->names->name($species, $locale),
            'forms' => $species->forms,
            'mega' => $species->isMega(),
            'superMega' => $species->superMega,
        ];
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
            'ivRankWithinFloor' => null === $outcome->withinFloor
                ? null
                : ['position' => $outcome->withinFloor->position, 'total' => $outcome->withinFloor->total],
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
     * @return array{cp: int, level: float, megaGainPercent: int|null}|null
     */
    private function target(?PowerUpTarget $target): ?array
    {
        return (
            null === $target
                ? null
                : [
                    'cp' => $target->cp->value,
                    'level' => $target->level->value,
                    'megaGainPercent' => $target->megaGainPercent,
                ]
        );
    }
}
