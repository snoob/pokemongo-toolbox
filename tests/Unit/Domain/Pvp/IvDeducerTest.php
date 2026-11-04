<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Pvp;

use App\Domain\Pokemon\Model\BaseStats;
use App\Domain\Pokemon\Model\IvFloor;
use App\Domain\Pokemon\Model\IvPattern;
use App\Domain\Pokemon\Model\IvSpread;
use App\Domain\Pvp\Exception\IvMismatch;
use App\Domain\Pvp\Model\Cp;
use App\Domain\Pvp\Model\IvDeduction;
use App\Domain\Pvp\Model\PokemonLevel;
use App\Domain\Pvp\Service\CpCalculator;
use App\Domain\Pvp\Service\IvDeducer;
use App\Tests\Fake\RealCpMultiplierTable;
use PHPUnit\Framework\TestCase;

final class IvDeducerTest extends TestCase
{
    private const int GENGAR_1_15_14_AT_19 = 1478;

    private CpCalculator $calculator;
    private IvDeducer $deducer;

    #[\Override]
    protected function setUp(): void
    {
        $multipliers = new RealCpMultiplierTable();
        $this->calculator = new CpCalculator($multipliers);
        $this->deducer = new IvDeducer($this->calculator, $multipliers);
    }

    public function testItFindsTheUnknownIvAndTheLevel(): void
    {
        $deduction = $this->deduce('1/x/14', self::GENGAR_1_15_14_AT_19);

        self::assertSame('1/15/14', (string) $deduction->iv);
        self::assertSame(['19'], array_map(strval(...), $deduction->levels));
        self::assertTrue($deduction->guessed);
    }

    public function testTypedIvsTheCpConfirmsAreNoGuess(): void
    {
        self::assertFalse($this->deduce('1/15/14', self::GENGAR_1_15_14_AT_19)->guessed);
    }

    public function testTypedIvsNoLevelGivesThatCpAreRefused(): void
    {
        try {
            $this->deduce('1/14/14', self::GENGAR_1_15_14_AT_19);
            self::fail('A misread IV must not pass for the Pokémon in hand.');
        } catch (IvMismatch $e) {
            self::assertSame([], $e->candidates);
            self::assertSame('No level gives CP 1478 with IVs 1/14/14; check the IVs and the CP.', $e->getMessage());
        }
    }

    public function testSeveralFittingSpreadsAreRefusedWithTheirList(): void
    {
        try {
            $this->deduce('x/x/14', self::GENGAR_1_15_14_AT_19);
            self::fail('A CP fitting several spreads must not pick one.');
        } catch (IvMismatch $e) {
            self::assertGreaterThan(1, \count($e->candidates));
            self::assertContains('1/15/14', array_map(
                static fn(IvDeduction $c): string => (string) $c->iv,
                $e->candidates,
            ));
        }
    }

    public function testTheFloorRulesOutSpreadsItCannotGive(): void
    {
        self::assertSame('1/15/14', (string) $this->deduce('1/15/x', self::GENGAR_1_15_14_AT_19)->iv);

        // The deducer is not told the attack is a 1 here; the floor alone rules it out.
        $this->expectException(IvMismatch::class);
        $this->deduce('x/15/x', self::GENGAR_1_15_14_AT_19, IvFloor::fromString('lucky'));
    }

    public function testLevel51IsOnlyTriedForABestBuddy(): void
    {
        $stats = new BaseStats(261, 149, 155);
        $cp = $this->calculator->cpAt($stats, new IvSpread(15, 15, 15), PokemonLevel::bestBuddyCap());
        $pattern = IvPattern::fromString('15/15/15');

        $deduction = $this->deducer->deduce($stats, $pattern, $cp, IvFloor::wild(), PokemonLevel::bestBuddyCap());
        self::assertSame(['51'], array_map(strval(...), $deduction->levels));

        $this->expectException(IvMismatch::class);
        $this->deducer->deduce($stats, $pattern, $cp, IvFloor::wild(), PokemonLevel::regularCap());
    }

    private function deduce(string $pattern, int $cp, ?IvFloor $floor = null): IvDeduction
    {
        return $this->deducer->deduce(
            new BaseStats(261, 149, 155),
            IvPattern::fromString($pattern),
            new Cp($cp),
            $floor ?? IvFloor::wild(),
            PokemonLevel::regularCap(),
        );
    }
}
