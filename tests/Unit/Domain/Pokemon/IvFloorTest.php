<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Pokemon;

use App\Domain\Pokemon\Exception\InvalidIv;
use App\Domain\Pokemon\Model\IvFloor;
use App\Domain\Pokemon\Model\IvSpread;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IvFloorTest extends TestCase
{
    #[DataProvider('sources')]
    public function testEachSourceStandsForTheFloorTheGameGuarantees(string $source, int $minimum): void
    {
        self::assertSame($minimum, IvFloor::fromString($source)->minimum);
    }

    /** @return iterable<string, array{string, int}> */
    public static function sources(): iterable
    {
        yield 'wild catch' => ['wild', 0];
        yield 'good friend' => ['good', 1];
        yield 'great friend' => ['great', 2];
        yield 'ultra friend' => ['ultra', 3];
        yield 'weather-boosted catch' => ['weather', 4];
        yield 'best friend' => ['best', 5];
        yield 'shadow raid' => ['shadow', 6];
        yield 'giovanni, same as a shadow' => ['giovanni', 6];
        yield 'raid' => ['raid', 10];
        yield 'egg, same as a raid' => ['egg', 10];
        yield 'research, same as a raid' => ['research', 10];
        yield 'lucky trade' => ['lucky', 12];
        yield 'any case' => [' Best ', 5];
    }

    public function testTheFloorItselfIsAcceptedForWhatNoSourceNames(): void
    {
        $floor = IvFloor::fromString('4');

        self::assertSame(4, $floor->minimum);
        self::assertSame('4', (string) $floor);
    }

    public function testASourceIsNamedWithTheFloorItStandsFor(): void
    {
        self::assertSame('best (5)', (string) IvFloor::fromString('best'));
    }

    #[DataProvider('rejectedFloors')]
    public function testItRefusesWhatIsNeitherASourceNorALegalIv(string $input): void
    {
        $this->expectException(InvalidIv::class);

        IvFloor::fromString($input);
    }

    /** @return iterable<string, array{string}> */
    public static function rejectedFloors(): iterable
    {
        yield 'unknown source' => ['friend'];
        yield 'above 15' => ['16'];
        yield 'negative' => ['-1'];
        yield 'empty' => [''];
    }

    public function testAllThreeIvsMustReachTheFloor(): void
    {
        $floor = IvFloor::fromString('best');

        self::assertTrue($floor->allows(new IvSpread(5, 5, 5)));
        self::assertTrue($floor->allows(new IvSpread(15, 6, 9)));
        self::assertFalse($floor->allows(new IvSpread(15, 15, 4)));
    }

    public function testOnlyAFloorAboveZeroLeavesSpreadsOut(): void
    {
        self::assertFalse(IvFloor::wild()->restricts());
        self::assertTrue(IvFloor::fromString('good')->restricts());
    }
}
