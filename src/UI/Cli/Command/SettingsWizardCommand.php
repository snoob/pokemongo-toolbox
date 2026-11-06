<?php

declare(strict_types=1);

namespace App\UI\Cli\Command;

use App\Domain\Weather\Exception\PlaceNotFound;
use App\Domain\Weather\Exception\WeatherUnavailable;
use App\Domain\Weather\Port\PlaceSearch;
use App\Infrastructure\Settings\SettingsFile;
use App\UI\Cli\Input\PlaceOption;
use App\UI\Cli\Settings\ConfigurableOptions;
use App\UI\Cli\Settings\LeaguePicker;
use App\UI\Cli\Settings\SettingKey;
use App\UI\Cli\Settings\SettingQuestion;
use App\UI\Cli\Settings\SettingsTable;
use App\UI\Cli\Settings\SettingValues;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * The three settings most players want, asked one after the other. It only writes what
 * `settings:set` would: nothing here is a setting of its own.
 */
#[AsCommand(
    name: 'app:settings:wizard',
    aliases: ['wizard', 'settings:wizard'],
    description: 'Set your account, leagues and place, question by question',
)]
final class SettingsWizardCommand extends Command
{
    private const array STANDARD_LEAGUES = ['great', 'ultra', 'master'];

    public function __construct(
        private readonly SettingsFile $settings,
        private readonly PlaceSearch $places,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this->setHelp(<<<'HELP'
            The way in: four questions that set the defaults most players want, each
            explaining what it is for, which commands use it and how to override it.

              1. the language Pokémon and moves are shown in      (global.locale)
              2. the game account the inventory commands use      (inventory.account)
              3. the leagues "pvp" ranks in, megas or not          (pvp.league)
              4. where you play, for the weather forecast          (weather.at)

            Enter keeps the value shown, "-" removes it. Nothing is saved before the last
            answer. Run it again any time: it starts from what is set.

            "settings --all" lists every option that can be set; any of them can also be
            written into the settings file by hand.
            HELP);
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$input->isInteractive()) {
            $io->error('The wizard asks questions: run it in a terminal.');

            return Command::INVALID;
        }

        try {
            $sections = $this->settings->load();
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->title('pogo — your defaults');
        $io->text([
            'Four questions to set what most players want. Each answer is only a default: it',
            'fills an option you leave out, and whatever you type on a command still wins.',
            'Enter keeps the value shown, "-" removes it. Nothing is saved before the end.',
        ]);
        $io->newLine();
        $this->current($io, $sections);

        $answers = [
            'global.locale' => SettingQuestion::ask(
                $this->explain($io, '1/4 · Language', [
                    'Pokémon and move names are shown in this language, whichever one you type them',
                    'in: "gengar" and "ectoplasma" find the same Pokémon. The rest of the interface',
                    'stays in English. Left unset, names follow the language you type. For another',
                    'language, pass it as an option:',
                    '  <info>pogo pvp ectoplasma --locale=en</info>',
                ]),
                'Which language should names be shown in by default, fr or en?',
                $sections[ConfigurableOptions::GLOBAL]['locale'] ?? null,
                null,
                static fn(string $answer): string => SettingValues::fromAnswer('locale', strtolower($answer)),
            ),
            'inventory.account' => SettingQuestion::ask(
                $this->explain($io, '2/4 · Account', [
                    'Your inventory is kept per game account. This one is used by every inventory',
                    'command — inventory:add, inventory:list, inventory:remove — whenever you leave',
                    'out --account. For another account, pass it as an option:',
                    '  <info>pogo inventory:list -a snoob2</info>',
                ]),
                'Which game account is the inventory for by default?',
                $sections['inventory']['account'] ?? null,
                'default',
                static fn(string $answer): string => SettingValues::fromAnswer('account', $answer),
            ),
            'pvp.league' => LeaguePicker::pick($input, $this->explain($io, '3/4 · Leagues', [
                'The "pvp" command ranks a Pokémon and its evolutions in these leagues whenever',
                'you leave out --league. Leave the Mega Editions unticked to keep megas',
                'out of sight; tick nothing to rank each species in its own leagues. For other',
                'leagues, pass them as an option:',
                '  <info>pogo pvp gengar -l mega-great</info>',
            ]), 'Which leagues should "pvp" rank in by default?', $sections['pvp']['league'] ?? null, self::STANDARD_LEAGUES),
            'weather.at' => SettingQuestion::ask(
                $this->explain($io, '4/4 · Where you play', [
                    'The "weather" command forecasts the in-game weather, to time the Daily Adventure',
                    'Incense. "auto" guesses where you are from your IP address — to the town at',
                    'best, and off behind a VPN or a mobile connection. For another place, or for',
                    'where you are now whatever is set, pass it as an option:',
                    '  <info>pogo weather --at="Lyon, France"</info>',
                    '  <info>pogo weather --at=auto</info>',
                ]),
                'Where do you play by default, e.g. "Villemomble, France"?',
                $sections['weather']['at'] ?? null,
                PlaceOption::AUTO,
                fn(string $answer): string => $this->place($io, $answer),
            ),
        ];

        foreach ($answers as $name => $value) {
            $key = SettingKey::fromString($name);

            if (null === $value) {
                unset($sections[$key->section][$key->option]);

                continue;
            }

            $sections[$key->section][$key->option] = $value;
        }

        try {
            $this->settings->save($sections);
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $this->recap($io, $answers);

        return Command::SUCCESS;
    }

    /**
     * The place as typed, once the forecast's own search finds it; offline, kept unchecked.
     *
     * @throws \InvalidArgumentException to ask again
     */
    private function place(SymfonyStyle $io, string $answer): string
    {
        if (PlaceOption::isAuto($answer)) {
            return PlaceOption::AUTO;
        }

        try {
            $io->text(\sprintf('<info>Found %s.</info>', $this->places->find($answer)->name));
        } catch (PlaceNotFound $e) {
            throw new \InvalidArgumentException($e->getMessage(), previous: $e);
        } catch (WeatherUnavailable) {
            $io->text('<comment>Cannot check it offline; kept as typed.</comment>');
        }

        return $answer;
    }

    /**
     * What a question is for, before it is asked; hands the style back to ask it with.
     *
     * @param list<string> $lines
     */
    private function explain(SymfonyStyle $io, string $title, array $lines): SymfonyStyle
    {
        $io->section($title);
        $io->text($lines);
        $io->newLine();

        return $io;
    }

    /**
     * What is set before any question, the settings the wizard does not ask about too.
     *
     * @param array<string, array<string, string|list<string>>> $sections
     */
    private function current(SymfonyStyle $io, array $sections): void
    {
        if ([] === $sections) {
            $io->text('Nothing is set yet.');

            return;
        }

        $io->text('Set now:');
        SettingsTable::render($io, SettingsTable::flatten($sections));
    }

    /**
     * @param array<string, null|string|list<string>> $answers
     */
    private function recap(SymfonyStyle $io, array $answers): void
    {
        SettingsTable::render($io, $answers);
        $io->success(\sprintf('Saved in %s.', $this->settings->path()));
        $io->text([
            'Where to go from here:',
            '  <info>pogo pvp gengar 1/15/14</info>                     rank a Pokémon and its IVs',
            '  <info>pogo inventory:add gengar 1/15/14 -l great</info>  track your best Pokémon per league',
            '  <info>pogo weather</info>                                time the Daily Adventure Incense',
            '  <info>pogo stardust</info>                               display information about Stardust income',
            '  <info>pogo settings</info>                               display your current configuration',
            '  <info>pogo settings --all</info>                         list every option you can set',
        ]);
        $io->newLine();
    }
}
