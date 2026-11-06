<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Stardust;

use App\Domain\Stardust\Service\BattleLeagueStardust;
use PHPUnit\Framework\TestCase;

final class BattleLeagueStardustTest extends TestCase
{
    public function testASetPaysItsFirstWinItsFifthAndItsEnd(): void
    {
        $sets = [];

        foreach (new BattleLeagueStardust()->sets() as $set) {
            $sets[$set->wins] = [$set->stardust->amount, $set->stardust->withStarPiece];
        }

        self::assertSame([1 => [7200, 10_800], 5 => [14_400, 21_600]], $sets);
    }

    public function testTheSeasonEndPaysByRank(): void
    {
        $ranks = [];

        foreach (new BattleLeagueStardust()->seasonEnd() as $season) {
            $ranks[$season->rank] = $season->stardust->amount;
        }

        self::assertSame(
            ['20' => 90_000, 'Ace' => 110_000, 'Veteran' => 134_000, 'Expert' => 164_000, 'Legend' => 200_000],
            $ranks,
        );
    }
}
