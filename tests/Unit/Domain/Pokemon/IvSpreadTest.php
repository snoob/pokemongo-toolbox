<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Pokemon;

use App\Domain\Pokemon\Exception\InvalidIv;
use App\Domain\Pokemon\Model\IvSpread;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IvSpreadTest extends TestCase
{
    #[DataProvider('acceptedNotations')]
    public function testItReadsTheUsualNotations(string $input): void
    {
        $iv = IvSpread::fromString($input);

        self::assertSame(1, $iv->attack);
        self::assertSame(15, $iv->defense);
        self::assertSame(14, $iv->stamina);
        self::assertSame('1/15/14', (string) $iv);
    }

    /** @return iterable<string, array{string}> */
    public static function acceptedNotations(): iterable
    {
        yield 'slashes' => ['1/15/14'];
        yield 'dashes' => ['1-15-14'];
        yield 'spaces' => ['1 15 14'];
        yield 'padded' => ['  1/15/14 '];
    }

    #[DataProvider('rejectedNotations')]
    public function testItRefusesWhatItCannotRead(string $input): void
    {
        $this->expectException(InvalidIv::class);

        IvSpread::fromString($input);
    }

    /** @return iterable<string, array{string}> */
    public static function rejectedNotations(): iterable
    {
        yield 'too few parts' => ['1/15'];
        yield 'too many parts' => ['1/15/14/0'];
        yield 'not a number' => ['a/15/14'];
        yield 'empty' => [''];
    }

    public function testAnIvCannotExistOutsideTheLegalRange(): void
    {
        try {
            new IvSpread(16, 0, 0);
            self::fail('An out-of-range IV must not produce an object.');
        } catch (InvalidIv $e) {
            self::assertSame('IV "attack" must be between 0 and 15, got 16.', $e->getMessage());
        }
    }

    public function testItEnumeratesEverySpreadExactlyOnce(): void
    {
        $seen = [];

        foreach (IvSpread::all() as $iv) {
            $seen[(string) $iv] = true;
        }

        self::assertCount(IvSpread::COMBINATIONS, $seen);
    }
}
