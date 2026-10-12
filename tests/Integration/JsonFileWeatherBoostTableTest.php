<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Domain\Pokemon\Model\PokemonType;
use App\Domain\Weather\Model\GameWeather;
use App\Infrastructure\Encounter\JsonFileWeatherBoostTable;
use PHPUnit\Framework\TestCase;

final class JsonFileWeatherBoostTableTest extends TestCase
{
    public function testItReadsTheAffinitiesTheBuildWrote(): void
    {
        $table = new JsonFileWeatherBoostTable(__DIR__ . '/../Fixtures/weather.json');

        self::assertSame(
            [PokemonType::Dragon, PokemonType::Flying, PokemonType::Psychic],
            $table->boostedTypes(GameWeather::Windy),
        );
    }

    public function testAWeatherMissingFromTheFileBoostsNothing(): void
    {
        $table = new JsonFileWeatherBoostTable(__DIR__ . '/../Fixtures/weather.json');

        self::assertSame([], $table->boostedTypes(GameWeather::Snow));
    }

    public function testAMissingFileSaysHowToRebuildIt(): void
    {
        try {
            new JsonFileWeatherBoostTable(__DIR__ . '/../Fixtures/absent.json')->boostedTypes(GameWeather::Fog);
            self::fail('a missing file cannot yield affinities');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('app:data:build', $e->getMessage());
        }
    }
}
