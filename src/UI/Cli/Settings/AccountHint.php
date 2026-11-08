<?php

declare(strict_types=1);

namespace App\UI\Cli\Settings;

use App\Infrastructure\Settings\SettingsFile;
use App\UI\Cli\Input\AccountOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * A reminder for the player who never said which account the inventory is for: the
 * commands fall back on "default" without asking, so they say it, and where to change it.
 */
final readonly class AccountHint
{
    public function __construct(
        private SettingsFile $settings,
    ) {}

    public function show(InputInterface $input, SymfonyStyle $io): void
    {
        if (
            AccountOption::typed($input)
            || null !== ($this->settings->load()[ConfigurableOptions::GLOBAL]['account'] ?? null)
        ) {
            return;
        }

        $io->text('<fg=gray>No account set, so "default" is used; "switch" sets yours.</>');
        $io->newLine();
    }
}
