<?php

declare(strict_types=1);

namespace App\UI\Cli\Presenter;

use App\Application\Weather\ForecastIncense\ForecastIncenseResult;
use App\Infrastructure\Naming\SpeciesNameResolver;
use Symfony\Component\Console\Style\SymfonyStyle;

final readonly class WeatherForecastTextPresenter
{
    public function __construct(
        private SpeciesNameResolver $names,
        private ForecastHourRowFormatter $hourRows,
    ) {}

    public function present(SymfonyStyle $io, ForecastIncenseResult $result, ?string $locale = null): void
    {
        $place = $result->place;

        $io->title('Daily Adventure Incense — weather forecast');
        $io->text(\sprintf('<info>%s</info>  (%.3F, %.3F)', $place->name, $place->latitude, $place->longitude));

        // An IP guess can land a town away, and a town away can be another weather cell.
        if ($result->guessedPlace) {
            $io->text(
                '<comment>Guessed from your IP address; if that is not where you play, pass --at="Town, Country".</comment>',
            );
        }

        $headers = ['Hour', 'Weather'];

        foreach ($result->targets as $target) {
            $headers[] = $this->names->name($target, $locale);
        }

        $rows = [];

        foreach ($result->hours as $hour) {
            $rows[] = $this->hourRows->format($hour, $result);
        }

        $io->table($headers, $rows);
    }
}
