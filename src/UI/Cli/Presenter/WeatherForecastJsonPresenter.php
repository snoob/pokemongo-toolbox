<?php

declare(strict_types=1);

namespace App\UI\Cli\Presenter;

use App\Application\Weather\ForecastIncense\ForecastIncenseResult;
use App\Domain\Pokemon\Model\PokemonType;
use App\Domain\Pokemon\Model\SpeciesId;
use App\Infrastructure\Naming\SpeciesNameResolver;

final readonly class WeatherForecastJsonPresenter
{
    public function __construct(
        private SpeciesNameResolver $names,
    ) {}

    public function present(ForecastIncenseResult $result, ?string $locale = null): string
    {
        return json_encode(
            [
                'place' => [
                    'name' => $result->place->name,
                    'latitude' => $result->place->latitude,
                    'longitude' => $result->place->longitude,
                    'guessed' => $result->guessedPlace,
                ],
                'targets' => $this->targets($result, $locale),
                'hours' => $this->hours($result),
            ],
            \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR,
        );
    }

    /**
     * @return list<array{id: string, name: string, types: list<string>}>
     */
    private function targets(ForecastIncenseResult $result, ?string $locale): array
    {
        $targets = [];

        foreach ($result->targets as $species) {
            $targets[] = [
                'id' => $species->id->value,
                'name' => $this->names->name($species, $locale),
                'types' => array_map(static fn(PokemonType $type): string => $type->value, $species->types),
            ];
        }

        return $targets;
    }

    /**
     * @return list<array{hour: string, weather: string|null, boosted: list<string>}>
     */
    private function hours(ForecastIncenseResult $result): array
    {
        $hours = [];

        foreach ($result->hours as $hour) {
            $hours[] = [
                'hour' => $hour->hour->format(\DATE_ATOM),
                'weather' => $hour->weather?->value,
                'boosted' => array_map(static fn(SpeciesId $id): string => $id->value, $hour->boosted),
            ];
        }

        return $hours;
    }
}
