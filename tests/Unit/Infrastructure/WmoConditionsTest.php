<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure;

use App\Domain\Weather\Model\GameWeather;
use App\Infrastructure\OpenMeteo\WmoConditions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WmoConditionsTest extends TestCase
{
    /**
     * @return iterable<string, array{int, GameWeather}>
     */
    public static function calmSkies(): iterable
    {
        yield 'clear sky' => [0, GameWeather::Clear];
        yield 'mainly clear' => [1, GameWeather::Clear];
        yield 'partly cloudy' => [2, GameWeather::PartlyCloudy];
        yield 'overcast' => [3, GameWeather::Overcast];
        yield 'fog' => [45, GameWeather::Fog];
        yield 'drizzle' => [53, GameWeather::Rainy];
        yield 'rain showers' => [80, GameWeather::Rainy];
        yield 'thunderstorm' => [95, GameWeather::Rainy];
        yield 'snow fall' => [73, GameWeather::Snow];
        yield 'snow showers' => [85, GameWeather::Snow];
    }

    #[DataProvider('calmSkies')]
    public function testEachCodeMapsToItsGameWeather(int $code, GameWeather $expected): void
    {
        self::assertSame($expected, new WmoConditions()->translate($code, 5.0, 10.0));
    }

    public function testStrongWindTurnsADrySkyWindy(): void
    {
        $conditions = new WmoConditions();

        self::assertSame(GameWeather::Windy, $conditions->translate(3, 24.1, 10.0), 'wind above 24 km/h');
        self::assertSame(GameWeather::Windy, $conditions->translate(0, 10.0, 35.1), 'gusts above 35 km/h');
        self::assertSame(GameWeather::Overcast, $conditions->translate(3, 24.0, 35.0), 'both thresholds are strict');
    }

    public function testPrecipitationOrFogNeverTurnsWindy(): void
    {
        $conditions = new WmoConditions();

        self::assertSame(GameWeather::Rainy, $conditions->translate(63, 60.0, 90.0));
        self::assertSame(GameWeather::Snow, $conditions->translate(75, 60.0, 90.0));
        self::assertSame(GameWeather::Fog, $conditions->translate(45, 60.0, 90.0));
    }

    public function testACodeOutsideTheWmoTableIsLeftUntranslated(): void
    {
        self::assertNull(new WmoConditions()->translate(4, 5.0, 5.0));
    }
}
