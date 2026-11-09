<?php

declare(strict_types=1);

namespace App\UI\Cli\Settings;

use App\Infrastructure\Settings\SettingsFile;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Stops a command when the settings hold a key that fills no option: skipped without a
 * word, it would leave the command on a default the player believes changed — the
 * inventory of another account. The error tells what `settings --fix` would do.
 */
final readonly class SettingsGuard
{
    public function __construct(
        private SettingsFile $settings,
        private SettingsRepair $repair,
    ) {}

    /**
     * @param array<string, array<string, string|list<string>>> $sections as the settings file holds them
     *
     * @return bool whether the command was stopped
     */
    public function stops(ConsoleCommandEvent $event, ?Application $application, array $sections): bool
    {
        $plan = null === $application ? null : $this->repair->plan($application, $sections);

        if (null === $plan || $plan->isEmpty()) {
            return false;
        }

        $output = $event->getOutput();
        $io = new SymfonyStyle(
            $event->getInput(),
            $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output,
        );
        SettingsRepairReport::explain($io, $plan, $this->settings->path(), SettingsRepairReport::binary($_SERVER));
        $event->disableCommand();

        return true;
    }
}
