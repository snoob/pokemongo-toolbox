<?php

declare(strict_types=1);

namespace App\UI\Cli\Settings;

use App\Infrastructure\Settings\SettingsFile;
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

    public function for(InputInterface $input): ?string
    {
        $typed = $input->hasParameterOption(['--account', '-a'], true);
        $set = null !== ($this->settings->load()['inventory']['account'] ?? null);

        return $typed || $set ? null : '<fg=gray>No account set, so "default" is used; "wizard" sets yours.</>';
    }

    public function show(InputInterface $input, SymfonyStyle $io): void
    {
        $hint = $this->for($input);

        if (null === $hint) {
            return;
        }

        $io->text($hint);
        $io->newLine();
    }
}
