<?php

declare(strict_types=1);

namespace App\Application\Weather\ForecastIncense;

use App\Domain\Encounter\Model\DailyAdventureIncense;
use App\Domain\Encounter\Service\WeatherBoost;
use App\Domain\Pokemon\Exception\SpeciesNotFound;
use App\Domain\Pokemon\Model\Species;
use App\Domain\Pokemon\Port\SpeciesCatalog;
use App\Domain\Weather\Model\HourlyWeather;
use App\Domain\Weather\Port\PlaceLocator;
use App\Domain\Weather\Port\PlaceSearch;
use App\Domain\Weather\Port\WeatherForecast;
use Psr\Clock\ClockInterface;

/**
 * When to light the Daily Adventure Incense: the coming hours of game weather, and which
 * of its rare targets each hour boosts.
 */
final readonly class ForecastIncenseHandler
{
    public function __construct(
        private PlaceSearch $places,
        private PlaceLocator $locator,
        private WeatherForecast $forecast,
        private SpeciesCatalog $species,
        private WeatherBoost $boost,
        private ClockInterface $clock,
    ) {}

    public function __invoke(ForecastIncenseQuery $query): ForecastIncenseResult
    {
        $targets = $this->targets();
        $place = null === $query->at ? $this->locator->here() : $this->places->find($query->at);

        return new ForecastIncenseResult(
            place: $place,
            guessedPlace: null === $query->at,
            targets: $targets,
            hours: array_map(fn(HourlyWeather $hour): HourOutcome => $this->hour(
                $hour,
                $targets,
            ), $this->upcoming($this->forecast->hourly($place))),
        );
    }

    /**
     * A forecast served from cache after a network failure may start in the past; an
     * hour that is already over is no longer a choice.
     *
     * @param list<HourlyWeather> $hours
     *
     * @return list<HourlyWeather>
     */
    private function upcoming(array $hours): array
    {
        $now = $this->clock->now();

        return array_values(array_filter(
            $hours,
            static fn(HourlyWeather $hour): bool => $hour->hour->modify('+1 hour') > $now,
        ));
    }

    /**
     * @param list<Species> $targets
     */
    private function hour(HourlyWeather $forecast, array $targets): HourOutcome
    {
        $weather = $forecast->weather;

        if (null === $weather) {
            return new HourOutcome($forecast->hour, null, []);
        }

        $boosted = [];

        foreach ($targets as $target) {
            if (!$this->boost->boosts($weather, $target)) {
                continue;
            }

            $boosted[] = $target->id;
        }

        return new HourOutcome($forecast->hour, $weather, $boosted);
    }

    /**
     * @return list<Species>
     */
    private function targets(): array
    {
        $targets = [];

        foreach (DailyAdventureIncense::rareTargets() as $id) {
            $targets[] = $this->species->find($id) ?? throw SpeciesNotFound::forIdentifier($id->value);
        }

        return $targets;
    }
}
