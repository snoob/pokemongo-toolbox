<?php

declare(strict_types=1);

namespace App\UI\Cli\Command;

use App\Infrastructure\Settings\SettingsFile;
use App\UI\Cli\Settings\ConfigurableOptions;
use App\UI\Cli\Settings\SettingsOverview;
use App\UI\Cli\Settings\SettingsRepair;
use App\UI\Cli\Settings\SettingsRepairPlan;
use App\UI\Cli\Settings\SettingsRepairReport;
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
        private readonly SettingsRepair $repair,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addOption('all', null, InputOption::VALUE_NONE, 'Also list the options that can be set')
            ->addOption('fix', null, InputOption::VALUE_NONE, 'Put right the keys this version no longer reads')
            ->setHelp(<<<'HELP'
                A setting is the default of a command option, named by the command family and
                the option: <info>pvp.floor</info> is --floor of "pvp", <info>global.account</info> is
                --account of every command having it. What you type always wins.

                  <info>%command.full_name% --all</info>

                When a version renames or removes an option, the old key stops every command
                and is listed here with what --fix will do: a renamed key keeps its value under
                its new name, any other is removed with its value quoted.

                  <info>%command.full_name% --fix</info>
                HELP);
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $application = $this->getApplication();

        try {
            $sections = $this->settings->load();
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $repair = null === $application ? null : $this->repair->plan($application, $sections);

        if (true === $input->getOption('fix')) {
            $fixed = $this->fix($io, $repair);

            if (null === $fixed) {
                return Command::FAILURE;
            }

            $sections = $fixed;
            $repair = null;
        }

        $configurable = null === $application ? [] : ConfigurableOptions::all($application);
        $warned = SettingsOverview::render(
            $io,
            $sections,
            $configurable,
            true === $input->getOption('all') ? $configurable : [],
            $this->settings->path(),
        );

        if (null !== $repair && !$repair->isEmpty()) {
            SettingsRepairReport::explain(
                $io,
                $repair,
                $this->settings->path(),
                SettingsRepairReport::binary($_SERVER),
            );

            return Command::FAILURE;
        }

        return $warned ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Writes the repaired settings, all at once, and says what was done.
     *
     * @return ?array<string, array<string, string|list<string>>> the settings now kept, null when they could not be written
     */
    private function fix(SymfonyStyle $io, ?SettingsRepairPlan $repair): ?array
    {
        if (null === $repair || $repair->isEmpty()) {
            $io->success('Nothing to fix.');

            return $this->settings->load();
        }

        try {
            $this->settings->save($repair->fixed);
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());

            return null;
        }

        SettingsRepairReport::done($io, $repair);

        return $repair->fixed;
    }
}
