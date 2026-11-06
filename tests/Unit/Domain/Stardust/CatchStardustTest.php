<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Stardust;

use App\Domain\Stardust\Model\CatchReward;
use App\Domain\Stardust\Service\CatchStardust;
use PHPUnit\Framework\TestCase;

final class CatchStardustTest extends TestCase
{
    public function testACatchPays100AndAQuarterMoreUnderAWeatherBoost(): void
    {
        self::assertSame([100, 150, 125, 187], $this->amounts(1), 'the Star Piece half of 125 rounds down');
    }

    public function testTheStarPieceIsRoundedOnEachCatch(): void
    {
        self::assertSame([10_000, 15_000, 12_500, 18_700], $this->amounts(100), '187 a catch, not 18,750');
    }

    public function testCountsGoFromOneToAHundred(): void
    {
        self::assertSame(
            [1, 10, 50, 100],
            array_map(static fn(CatchReward $reward): int => $reward->catches, new CatchStardust()->rewards()),
        );
    }

    /**
     * @return list<int> Stardust and Star Piece, then the same under a weather boost
     */
    private function amounts(int $catches): array
    {
        foreach (new CatchStardust()->rewards() as $reward) {
            if ($reward->catches === $catches) {
                return [
                    $reward->stardust->amount,
                    $reward->stardust->withStarPiece,
                    $reward->weatherBoosted->amount,
                    $reward->weatherBoosted->withStarPiece,
                ];
            }
        }

        self::fail(\sprintf('No reward for %d catches.', $catches));
    }
}
