<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Pvp;

use App\Domain\Pokemon\Model\BaseStats;
use App\Domain\Pokemon\Model\IvSpread;
use App\Domain\Pvp\Exception\SpeciesIneligibleForLeague;
use App\Domain\Pvp\Model\League;
use App\Domain\Pvp\Model\PokemonLevel;
use App\Domain\Pvp\Model\RankedSpread;
use App\Domain\Pvp\Service\CpCalculator;
use App\Domain\Pvp\Service\IvRankCalculator;
use App\Tests\Fake\RealCpMultiplierTable;
use PHPUnit\Framework\TestCase;

final class IvRankCalculatorTest extends TestCase
{
    private const array GENGAR = [261, 149, 155];

    private IvRankCalculator $calculator;

    #[\Override]
    protected function setUp(): void
    {
        $multipliers = new RealCpMultiplierTable();
        $this->calculator = new IvRankCalculator(new CpCalculator($multipliers), $multipliers);
    }

    public function testItPlacesAKnownSpreadWhereTheReferenceToolsDo(): void
    {
        $ranking = $this->calculator->rank($this->gengar(), League::Great, PokemonLevel::regularCap());

        $spread = $ranking->for(new IvSpread(1, 15, 14));

        self::assertSame(130, $spread->position);
        self::assertSame(IvSpread::COMBINATIONS, $spread->total);
        self::assertSame(1478, $spread->cp->value);
        self::assertSame('19', (string) $spread->level);
        self::assertEqualsWithDelta(98.02, $spread->percentOfBest, 0.01);
    }

    public function testTheBestSpreadIsTheHundredPercentReference(): void
    {
        $best = $this->calculator->rank($this->gengar(), League::Ultra, PokemonLevel::regularCap())->best();

        self::assertSame(1, $best->position);
        self::assertTrue($best->isPerfect());
        self::assertEqualsWithDelta(100.0, $best->percentOfBest, 0.0001);
    }

    public function testEverySpreadGetsOneContiguousPositionWithNoGapOrDuplicate(): void
    {
        $ranking = $this->calculator->rank($this->gengar(), League::Great, PokemonLevel::regularCap());

        $positions = array_map(static fn(RankedSpread $spread): int => $spread->position, $ranking->spreads);

        self::assertCount(IvSpread::COMBINATIONS, $positions);
        self::assertSame(range(1, IvSpread::COMBINATIONS), $positions);
    }

    public function testRanksNeverImproveAsStatProductDrops(): void
    {
        $spreads = $this->calculator->rank($this->gengar(), League::Great, PokemonLevel::regularCap())->spreads;

        $previous = \PHP_FLOAT_MAX;

        foreach ($spreads as $spread) {
            self::assertLessThanOrEqual($previous, $spread->statProduct);
            $previous = $spread->statProduct;
        }
    }

    public function testInAnUncappedLeagueEverySpreadReachesTheLevelCap(): void
    {
        $ranking = $this->calculator->rank($this->gengar(), League::Master, PokemonLevel::regularCap());

        self::assertFalse(League::Master->ranksIvSpreads());
        self::assertSame(new IvSpread(15, 15, 15)->attack, $ranking->best()->iv->attack);

        foreach ($ranking->top(50) as $spread) {
            self::assertSame('50', (string) $spread->level);
        }
    }

    public function testBestBuddyPushesTheReachableLevelOneStepFurther(): void
    {
        $regular = $this->calculator->rank($this->gengar(), League::Master, PokemonLevel::regularCap())->best();
        $bestBuddy = $this->calculator->rank($this->gengar(), League::Master, PokemonLevel::bestBuddyCap())->best();

        self::assertSame('50', (string) $regular->level);
        self::assertSame('51', (string) $bestBuddy->level);
        self::assertGreaterThan($regular->cp->value, $bestBuddy->cp->value);
    }

    public function testASpeciesTooStrongForTheCapIsReportedAsIneligible(): void
    {
        $this->expectException(SpeciesIneligibleForLeague::class);

        // Stats far beyond anything in the game: even level 1 busts the Great League cap.
        $this->calculator->rank(new BaseStats(5000, 5000, 5000), League::Great, PokemonLevel::regularCap());
    }

    private function gengar(): BaseStats
    {
        return new BaseStats(...self::GENGAR);
    }
}
