<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application;

use App\Application\Weather\ForecastIncense\ForecastIncenseHandler;
use App\Application\Weather\ForecastIncense\ForecastIncenseQuery;
use App\Application\Weather\ForecastIncense\ForecastIncenseResult;
use App\Application\Weather\ForecastIncense\HourOutcome;
use App\Domain\Encounter\Service\WeatherBoost;
use App\Domain\Pokemon\Exception\SpeciesNotFound;
use App\Domain\Pokemon\Model\BaseStats;
use App\Domain\Pokemon\Model\DexNumber;
use App\Domain\Pokemon\Model\PokemonType;
use App\Domain\Pokemon\Model\Species;
use App\Domain\Pokemon\Model\SpeciesId;
use App\Domain\Weather\Exception\PlaceNotFound;
use App\Domain\Weather\Model\GameWeather;
use App\Domain\Weather\Model\HourlyWeather;
use App\Domain\Weather\Model\Place;
use App\Tests\Fake\InMemoryPlaces;
use App\Tests\Fake\InMemorySpeciesCatalog;
use App\Tests\Fake\InMemoryWeatherForecast;
use App\Tests\Fake\RealWeatherBoostTable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class ForecastIncenseHandlerTest extends TestCase
{
    private const string NOW = '2026-10-10 14:20:00 Europe/Paris';

    public function testWithoutAPlaceTheGuessIsForecastAndFlaggedAsAGuess(): void
    {
        $forecast = new InMemoryWeatherForecast([$this->hour('15:00', GameWeather::Clear)]);

        $result = $this->handler($forecast)(new ForecastIncenseQuery());

        self::assertSame('Ponthierry, FR', $result->place->name);
        self::assertTrue($result->guessedPlace);
        self::assertSame($result->place, $forecast->askedFor);
    }

    public function testANamedPlaceWinsOverTheGuess(): void
    {
        $result = $this->handler()(new ForecastIncenseQuery('villemomble, france'));

        self::assertSame('Villemomble, France', $result->place->name);
        self::assertFalse($result->guessedPlace);
    }

    public function testAnUnknownPlaceIsReportedRatherThanReplacedByTheGuess(): void
    {
        $this->expectException(PlaceNotFound::class);

        $this->handler()(new ForecastIncenseQuery('Atlantis'));
    }

    public function testEachHourListsTheBirdsItsWeatherBoosts(): void
    {
        $result = $this->handler(new InMemoryWeatherForecast([
            $this->hour('15:00', GameWeather::Windy),
            $this->hour('16:00', GameWeather::Overcast),
            $this->hour('17:00', GameWeather::Rainy),
        ]))(new ForecastIncenseQuery());

        self::assertCount(3, $this->hourAt($result, 0)->boosted, 'all three birds are part Flying');
        self::assertSame(
            ['zapdos_galarian'],
            array_map(strval(...), $this->hourAt($result, 1)->boosted),
            'only Zapdos is Fighting',
        );
        self::assertSame([], $this->hourAt($result, 2)->boosted);
    }

    public function testAnHourIsWorthALookWhenItBoostsAtLeastOneBird(): void
    {
        $forecast = new InMemoryWeatherForecast([
            $this->hour('15:00', GameWeather::Windy),
            $this->hour('16:00', GameWeather::Rainy),
            $this->hour('17:00', GameWeather::Fog),
        ]);

        $hours = $this->handler($forecast)(new ForecastIncenseQuery())->hours;

        self::assertSame([true, false, true], array_map(static fn(HourOutcome $h): bool => $h->boostsAny(), $hours));
    }

    public function testAnUntranslatedHourHasNoVerdict(): void
    {
        $result = $this->handler(new InMemoryWeatherForecast([$this->hour('15:00', null)]))(new ForecastIncenseQuery());

        self::assertFalse($this->hourAt($result, 0)->isKnown());
        self::assertFalse($this->hourAt($result, 0)->boostsAny(), 'an unknown sky boosts nobody by default');
    }

    public function testHoursAlreadyOverAreDropped(): void
    {
        $forecast = new InMemoryWeatherForecast([
            $this->hour('13:00', GameWeather::Clear),
            $this->hour('14:00', GameWeather::Clear),
            $this->hour('15:00', GameWeather::Clear),
        ]);

        $result = $this->handler($forecast)(new ForecastIncenseQuery());

        self::assertSame(['14:00', '15:00'], $this->times($result->hours), 'the hour under way still counts');
    }

    public function testAMissingTargetPointsAtStaleGameData(): void
    {
        $this->expectException(SpeciesNotFound::class);

        $handler = new ForecastIncenseHandler(
            $this->places(),
            $this->places(),
            new InMemoryWeatherForecast(),
            new InMemorySpeciesCatalog([]),
            new WeatherBoost(new RealWeatherBoostTable()),
            new MockClock(self::NOW),
        );

        $handler(new ForecastIncenseQuery());
    }

    private function handler(?InMemoryWeatherForecast $forecast = null): ForecastIncenseHandler
    {
        return new ForecastIncenseHandler(
            $this->places(),
            $this->places(),
            $forecast ?? new InMemoryWeatherForecast([$this->hour('15:00', GameWeather::Clear)]),
            new InMemorySpeciesCatalog([
                $this->bird('articuno_galarian', 144, new BaseStats(250, 197, 207), PokemonType::Psychic),
                $this->bird('zapdos_galarian', 145, new BaseStats(252, 189, 207), PokemonType::Fighting),
                $this->bird('moltres_galarian', 146, new BaseStats(202, 231, 207), PokemonType::Dark),
            ]),
            new WeatherBoost(new RealWeatherBoostTable()),
            new MockClock(self::NOW),
        );
    }

    private function hourAt(ForecastIncenseResult $result, int $index): HourOutcome
    {
        return $result->hours[$index] ?? self::fail(\sprintf('no hour at position %d', $index));
    }

    /**
     * @param list<HourOutcome> $hours
     *
     * @return list<string>
     */
    private function times(array $hours): array
    {
        return array_map(static fn(HourOutcome $h): string => $h->hour->format('H:i'), $hours);
    }

    private function places(): InMemoryPlaces
    {
        return new InMemoryPlaces(new Place('Ponthierry, FR', 48.5337, 2.5442), ['Villemomble, France' => new Place(
            'Villemomble, France',
            48.883,
            2.508,
        )]);
    }

    private function bird(string $id, int $dex, BaseStats $stats, PokemonType $type): Species
    {
        return new Species(
            new SpeciesId($id),
            new DexNumber($dex),
            $stats,
            false,
            forms: ['galarian'],
            types: [$type, PokemonType::Flying],
        );
    }

    private function hour(string $time, ?GameWeather $weather): HourlyWeather
    {
        return new HourlyWeather(new \DateTimeImmutable('2026-10-10 ' . $time . ' Europe/Paris'), $weather);
    }
}
