<?php

declare(strict_types=1);

namespace App\Domain\Stardust\Service;

use App\Domain\Stardust\Model\BattleSetReward;
use App\Domain\Stardust\Model\SeasonReward;
use App\Domain\Stardust\Model\Stardust;

/**
 * Stardust earned in the GO Battle League.
 *
 * A set of five battles pays 3,600 on its first win, 7,200 on its fifth, and 3,600 more
 * once it ends; the wins between pay no Stardust. The season pays once more on its last
 * day, by the rank reached.
 *
 * Provenance: the player's observations in the game, no upstream file — see specs/stardust.md.
 */
final readonly class BattleLeagueStardust
{
    private const int FIRST_WIN = 3600;
    private const int FIFTH_WIN = 7200;
    private const int SET_END = 3600;

    /**
     * Pairs rather than a map: PHP would turn the key '20' into an integer.
     *
     * @var non-empty-list<array{non-empty-string, int}>
     */
    private const array SEASON_END = [
        ['20', 90_000],
        ['Ace', 110_000],
        ['Veteran', 134_000],
        ['Expert', 164_000],
        ['Legend', 200_000],
    ];

    /**
     * @return non-empty-list<BattleSetReward>
     */
    public function sets(): array
    {
        $oneWin = Stardust::reward(self::FIRST_WIN)->plus(Stardust::reward(self::SET_END));

        return [
            new BattleSetReward(1, $oneWin),
            new BattleSetReward(5, $oneWin->plus(Stardust::reward(self::FIFTH_WIN))),
        ];
    }

    /**
     * @return non-empty-list<SeasonReward>
     */
    public function seasonEnd(): array
    {
        return array_map(
            /** @param array{non-empty-string, int} $rank */
            static fn(array $rank): SeasonReward => new SeasonReward($rank[0], Stardust::reward($rank[1])),
            self::SEASON_END,
        );
    }
}
