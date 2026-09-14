<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Pvp;

use App\Domain\Pokemon\Model\BaseStats;
use App\Domain\Pokemon\Model\IvSpread;
use App\Domain\Pvp\Model\Cp;
use App\Domain\Pvp\Model\PokemonLevel;
use App\Domain\Pvp\Service\CpCalculator;
use App\Tests\Fake\RealCpMultiplierTable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CpCalculatorTest extends TestCase
{
    private CpCalculator $calculator;

    #[\Override]
    protected function setUp(): void
    {
        $this->calculator = new CpCalculator(new RealCpMultiplierTable());
    }

    /**
     * Values published for these species; if the formula drifts, these break first.
     */
    #[DataProvider('knownMaximumCp')]
    public function testItReproducesPublishedCp(BaseStats $stats, float $level, int $expected): void
    {
        $cp = $this->calculator->cpAt($stats, new IvSpread(15, 15, 15), new PokemonLevel($level));

        self::assertSame($expected, $cp->value);
    }

    /** @return iterable<string, array{BaseStats, float, int}> */
    public static function knownMaximumCp(): iterable
    {
        yield 'Gengar at level 40' => [new BaseStats(261, 149, 155), 40.0, 2878];
        yield 'Gengar at level 50' => [new BaseStats(261, 149, 155), 50.0, 3254];
        yield 'Alakazam at level 40' => [new BaseStats(271, 167, 146), 40.0, 3057];
    }

    public function testCpNeverFallsBelowTheGameFloor(): void
    {
        $cp = $this->calculator->cpAt(new BaseStats(1, 1, 1), new IvSpread(0, 0, 0), new PokemonLevel(1.0));

        self::assertSame(Cp::FLOOR, $cp->value);
    }

    public function testStatProductFloorsHitPointsBecauseTheGameShowsWholeHp(): void
    {
        $stats = new BaseStats(261, 149, 155);
        $iv = new IvSpread(1, 15, 14);
        $level = new PokemonLevel(19.0);
        $multiplier = new RealCpMultiplierTable()->multiplierFor($level);

        $expected = (261 + 1) * $multiplier * ((149 + 15) * $multiplier) * floor((155 + 14) * $multiplier);

        self::assertEqualsWithDelta($expected, $this->calculator->statProductAt($stats, $iv, $level), 0.000_001);
    }
}
