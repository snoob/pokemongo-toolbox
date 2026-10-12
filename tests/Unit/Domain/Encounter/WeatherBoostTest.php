<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Encounter;

use App\Domain\Encounter\Service\WeatherBoost;
use App\Domain\Pokemon\Model\BaseStats;
use App\Domain\Pokemon\Model\DexNumber;
use App\Domain\Pokemon\Model\PokemonType;
use App\Domain\Pokemon\Model\Species;
use App\Domain\Pokemon\Model\SpeciesId;
use App\Domain\Weather\Model\GameWeather;
use App\Tests\Fake\RealWeatherBoostTable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WeatherBoostTest extends TestCase
{
    /**
     * @return iterable<string, array{GameWeather, bool}>
     */
    public static function galarianZapdosWeathers(): iterable
    {
        yield 'windy favours its Flying type' => [GameWeather::Windy, true];
        yield 'cloudy favours its Fighting type' => [GameWeather::Overcast, true];
        yield 'fog favours neither of its types' => [GameWeather::Fog, false];
        yield 'clear favours neither of its types' => [GameWeather::Clear, false];
    }

    #[DataProvider('galarianZapdosWeathers')]
    public function testEitherTypeIsEnoughToBeBoosted(GameWeather $weather, bool $boosted): void
    {
        $zapdos = $this->species('zapdos_galarian', PokemonType::Fighting, PokemonType::Flying);

        self::assertSame($boosted, new WeatherBoost(new RealWeatherBoostTable())->boosts($weather, $zapdos));
    }

    public function testASpeciesWithoutKnownTypesIsNeverBoosted(): void
    {
        $untyped = $this->species('mystery');

        foreach (GameWeather::cases() as $weather) {
            self::assertFalse(new WeatherBoost(new RealWeatherBoostTable())->boosts($weather, $untyped));
        }
    }

    private function species(string $id, PokemonType ...$types): Species
    {
        return new Species(
            new SpeciesId($id),
            new DexNumber(145),
            new BaseStats(252, 189, 207),
            false,
            types: array_values($types),
        );
    }
}
