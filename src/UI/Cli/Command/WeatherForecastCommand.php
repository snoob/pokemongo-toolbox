<?php

declare(strict_types=1);

namespace App\UI\Cli\Command;

use App\Application\Weather\ForecastIncense\ForecastIncenseHandler;
use App\Application\Weather\ForecastIncense\ForecastIncenseQuery;
use App\Domain\Pokemon\Exception\SpeciesNotFound;
use App\Domain\Weather\Exception\PlaceNotFound;
use App\Domain\Weather\Exception\WeatherUnavailable;
use App\Infrastructure\Json\JsonValue;
use App\UI\Cli\Presenter\WeatherForecastJsonPresenter;
use App\UI\Cli\Presenter\WeatherForecastTextPresenter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'pogo:weather:forecast',
    aliases: ['weather'],
    description: 'In-game weather for the coming hours, and when to light the Daily Adventure Incense',
)]
final class WeatherForecastCommand extends Command
{
    public function __construct(
        private readonly ForecastIncenseHandler $handler,
        private readonly WeatherForecastTextPresenter $text,
        private readonly WeatherForecastJsonPresenter $json,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addOption(
                'at',
                'a',
                InputOption::VALUE_REQUIRED,
                'Where you will play, e.g. "Villemomble, France" (defaults to a guess from your IP address)',
            )
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Output format: text or json', 'text')
            ->addOption('locale', null, InputOption::VALUE_REQUIRED, 'Language of Pokémon names: fr or en')
            ->setHelp(<<<'HELP'
                The forecast comes from Open-Meteo, not from AccuWeather, which the game
                reads: an hour can still disagree with what the game shows.

                Examples:

                  <info>%command.full_name%</info>                             where your IP address says you are
                  <info>%command.full_name% --at="Villemomble, France"</info>  a place you name
                HELP);
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $result = ($this->handler)(new ForecastIncenseQuery(JsonValue::asString($input->getOption('at'))));
        } catch (PlaceNotFound|WeatherUnavailable|SpeciesNotFound $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $locale = JsonValue::asString($input->getOption('locale'));

        'json' === $input->getOption('format')
            ? $output->writeln($this->json->present($result, $locale))
            : $this->text->present($io, $result, $locale);

        return Command::SUCCESS;
    }
}
