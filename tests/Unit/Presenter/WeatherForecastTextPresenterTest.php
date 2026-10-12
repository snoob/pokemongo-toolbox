<?php

declare(strict_types=1);

namespace App\Tests\Unit\Presenter;

use App\Application\Weather\ForecastIncense\ForecastIncenseResult;
use App\Application\Weather\ForecastIncense\HourOutcome;
use App\Domain\Pokemon\Model\BaseStats;
use App\Domain\Pokemon\Model\DexNumber;
use App\Domain\Pokemon\Model\Species;
use App\Domain\Pokemon\Model\SpeciesId;
use App\Domain\Weather\Model\GameWeather;
use App\Domain\Weather\Model\Place;
use App\Infrastructure\Naming\SpeciesNameResolver;
use App\Tests\Fake\FixtureTranslator;
use App\UI\Cli\Presenter\ForecastHourRowFormatter;
use App\UI\Cli\Presenter\WeatherForecastTextPresenter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

final class WeatherForecastTextPresenterTest extends TestCase
{
    public function testAGuessedPlaceTellsHowToCorrectIt(): void
    {
        self::assertStringContainsString('--at=', $this->render(guessed: true));
        self::assertStringNotContainsString('--at=', $this->render(guessed: false));
    }

    public function testEachHourShowsItsWeatherAndTheBirdsItBoosts(): void
    {
        $output = $this->render();

        self::assertMatchesRegularExpression('/Windy\s+✔/u', $output, 'a boosted bird is ticked');
        self::assertMatchesRegularExpression('/Rain\s+–/u', $output, 'an unboosted one gets a dash');
    }

    public function testAnHourBoostingABirdIsHighlighted(): void
    {
        $output = $this->render(decorated: true);

        self::assertMatchesRegularExpression('/\e\[32mSat 15:00/', $output, 'an hour boosting a bird stands out');
        self::assertDoesNotMatchRegularExpression('/\e\[32mSat 16:00/', $output);
    }

    private function render(bool $guessed = false, bool $decorated = false): string
    {
        $zapdos = new Species(
            new SpeciesId('zapdos_galarian'),
            new DexNumber(145),
            new BaseStats(252, 189, 207),
            false,
        );
        $result = new ForecastIncenseResult(
            new Place('Villemomble, France', 48.883, 2.508),
            $guessed,
            [$zapdos],
            [
                new HourOutcome(new \DateTimeImmutable('2026-10-10 15:00'), GameWeather::Windy, [$zapdos->id]),
                new HourOutcome(new \DateTimeImmutable('2026-10-10 16:00'), GameWeather::Rainy, []),
            ],
        );

        $output = new BufferedOutput(decorated: $decorated);
        new WeatherForecastTextPresenter(
            new SpeciesNameResolver(FixtureTranslator::create('en')),
            new ForecastHourRowFormatter(),
        )->present(new SymfonyStyle(new ArrayInput([]), $output), $result);

        return $output->fetch();
    }
}
