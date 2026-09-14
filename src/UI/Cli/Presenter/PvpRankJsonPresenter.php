<?php

declare(strict_types=1);

namespace App\UI\Cli\Presenter;

use App\Application\Pvp\RankPokemon\LeagueOutcome;
use App\Application\Pvp\RankPokemon\RankPokemonResult;
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
            $leagues[$outcome->league->value] = $this->league($outcome);
        }

        return json_encode(
            [
                'species' => [
                    'id' => $result->species->id->value,
                    'dex' => $result->species->dex->value,
                    'name' => $this->names->name($result->species, $locale),
                    'forms' => $result->species->forms,
                ],
                'iv' => null === $result->iv ? null : (string) $result->iv,
                'levelCap' => $result->levelCap->value,
                'leagues' => $leagues,
            ],
            \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR,
        );
    }

    /**
     * @return array{speciesRank: array{position: int, total: int, score: float}|null, ineligible: bool, ivRank: array{position: int, total: int, percentOfBest: float, statProduct: float, cp: int, level: float}|null}
     */
    private function league(LeagueOutcome $outcome): array
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
                ],
            'ineligible' => $outcome->ineligible,
            'ivRank' => null === $spread
                ? null
                : [
                    'position' => $spread->position,
                    'total' => $spread->total,
                    'percentOfBest' => round($spread->percentOfBest, 4),
                    'statProduct' => round($spread->statProduct, 4),
                    'cp' => $spread->cp->value,
                    'level' => $spread->level->value,
                ],
        ];
    }
}
