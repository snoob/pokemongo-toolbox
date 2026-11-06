<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Stardust;

use App\Domain\Stardust\Model\FedDefender;
use App\Domain\Stardust\Service\BerryStardust;
use PHPUnit\Framework\TestCase;

final class BerryStardustTest extends TestCase
{
    public function testTheStreakCarriesOverFromOneDefenderToTheNext(): void
    {
        $gym = new BerryStardust()->feedFullGym();

        self::assertSame(
            [1650, 4650, 7650, 10_650, 13_650, 16_650],
            array_map(static fn(FedDefender $defender): int => $defender->stardust->amount, $gym->defenders),
            '30 + 60 + … + 300 for the first, 330 … 600 for the second',
        );
    }

    public function testAFullGymPays54900(): void
    {
        $gym = new BerryStardust()->feedFullGym();

        self::assertSame(60, $gym->berries());
        self::assertSame(54_900, $gym->stardust()->amount, '30 × (1 + 2 + … + 60)');
        self::assertSame(82_350, $gym->stardust()->withStarPiece);
    }

    public function testASmallerGymReadsOffTheFirstDefenders(): void
    {
        $three = new BerryStardust()
            ->feedFullGym()
            ->stardustUpTo(3);

        self::assertSame(13_950, $three->amount);
        self::assertSame(20_925, $three->withStarPiece);
    }
}
