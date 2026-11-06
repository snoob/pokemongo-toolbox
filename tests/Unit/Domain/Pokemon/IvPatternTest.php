<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Pokemon;

use App\Domain\Pokemon\Exception\InvalidIv;
use App\Domain\Pokemon\Model\IvPattern;
use App\Domain\Pokemon\Model\IvSpread;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IvPatternTest extends TestCase
{
    #[DataProvider('unknownNotations')]
    public function testItReadsAnUnknownIv(string $input): void
    {
        $pattern = IvPattern::fromString($input);

        self::assertSame(1, $pattern->attack);
        self::assertNull($pattern->defense);
        self::assertSame(14, $pattern->stamina);
        self::assertFalse($pattern->isComplete());
        self::assertSame('1/x/14', (string) $pattern);
    }

    /** @return iterable<string, array{string}> */
    public static function unknownNotations(): iterable
    {
        yield 'x' => ['1/x/14'];
        yield 'capital X' => ['1/X/14'];
        yield 'other separators' => ['1-x 14'];
    }

    public function testACompletePatternIsASpread(): void
    {
        $pattern = IvPattern::fromString('1/15/14');

        self::assertTrue($pattern->isComplete());
        self::assertTrue(new IvSpread(1, 15, 14)->equals($pattern->toSpread() ?? new IvSpread(0, 0, 0)));
    }

    public function testAnIncompletePatternIsNoSpread(): void
    {
        self::assertNull(IvPattern::fromString('1/x/14')->toSpread());
    }

    public function testItMatchesWhateverTheUnknownIs(): void
    {
        $pattern = IvPattern::fromString('1/x/14');

        self::assertTrue($pattern->matches(new IvSpread(1, 0, 14)));
        self::assertTrue($pattern->matches(new IvSpread(1, 15, 14)));
        self::assertFalse($pattern->matches(new IvSpread(2, 15, 14)));
    }

    #[DataProvider('rejectedNotations')]
    public function testItRefusesWhatItCannotRead(string $input): void
    {
        $this->expectException(InvalidIv::class);

        IvPattern::fromString($input);
    }

    /** @return iterable<string, array{string}> */
    public static function rejectedNotations(): iterable
    {
        yield 'too few parts' => ['1/x'];
        yield 'two unknowns glued' => ['1/xx/14'];
        yield 'out of range' => ['1/x/16'];
        yield 'not a number' => ['a/15/14'];
        yield 'question mark' => ['1/?/14'];
        yield 'star' => ['1/*/14'];
        yield 'underscore' => ['1/_/14'];
    }
}
