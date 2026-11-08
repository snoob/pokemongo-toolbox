<?php

declare(strict_types=1);

namespace App\UI\Cli\Command;

use App\Infrastructure\Settings\SettingsFile;
use App\UI\Cli\Settings\ConfigurableOptions;
use App\UI\Cli\Settings\SettingsTable;
use App\UI\Cli\Settings\SettingValues;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:settings:show',
    aliases: ['settings', 'settings:show'],
    description: 'Your default for each option, and the file that keeps them',
)]
final class SettingsShowCommand extends Command
{
    public function __construct(
        private readonly SettingsFile $settings,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this->addOption('all', null, InputOption::VALUE_NONE, 'Also list the options that can be set')->setHelp(
            <<<'HELP'
                A setting is the default of a command option, named by the command family and
                the option: <info>pvp.floor</info> is --floor of "pvp", <info>global.account</info> is
                --account of every command having it. What you type always wins.

                  <info>%command.full_name% --all</info>
                HELP,
        );
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $sections = $this->settings->load();
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $rows = [];
        $application = $this->getApplication();
        $configurable = null === $application ? [] : ConfigurableOptions::all($application);

        $problems = [];

        foreach (SettingsTable::flatten($sections) as $key => $value) {
            $problem = self::problem($key, explode('.', $key, 2)[1] ?? $key, (array) $value, $configurable);
            $rows[$key] = [
                null === $problem ? $key : \sprintf('<comment>%s</>', $key),
                implode(' ', (array) $value),
                '',
            ];

            if (null !== $problem) {
                $problems[] = \sprintf('%s: %s', $key, $problem);
            }
        }

        if (true === $input->getOption('all')) {
            foreach ($configurable as $key => $options) {
                $rows[$key] = [$key, $rows[$key][1] ?? '', $options[0]->getDescription()];
            }

            ksort($rows);
        }

        [] === $rows
            ? $io->text('Nothing set yet; "wizard" sets the usual defaults. "--all" lists what can be set.')
            : $io->table(['Setting', 'Value', true === $input->getOption('all') ? 'Option' : ''], array_values($rows));

        $io->text(\sprintf('<fg=gray>Kept in %s</>', $this->settings->path()));

        if ([] !== $rows) {
            $io->text('<fg=gray>"wizard" sets them up again, question by question.</>');
        }
        $io->newLine();

        if ([] !== $problems) {
            $io->warning($problems);

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * What is wrong with a setting edited by hand, null when nothing is.
     *
     * @param list<string>                      $values
     * @param array<string, list<InputOption>> $configurable
     */
    private static function problem(string $key, string $option, array $values, array $configurable): ?string
    {
        if (!\array_key_exists($key, $configurable)) {
            return 'no such option; remove it from the file.';
        }

        try {
            SettingValues::check($option, $values);
        } catch (\InvalidArgumentException $e) {
            return $e->getMessage();
        }

        return null;
    }
}
