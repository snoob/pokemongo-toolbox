<?php

declare(strict_types=1);

namespace App\UI\Cli\Settings;

use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Tells a `SettingsRepairPlan`: before, what is wrong and what `--fix` will do; after,
 * the same actions as done.
 */
final readonly class SettingsRepairReport
{
    /**
     * @param string $binary as the player launched it, for the command to type
     */
    public static function explain(SymfonyStyle $io, SettingsRepairPlan $plan, string $path, string $binary): void
    {
        $io->error(\sprintf('Your settings file %s holds keys this version no longer reads.', $path));
        $width = max(array_map(strlen(...), array_keys($plan->problems)));

        foreach ($plan->problems as $key => $why) {
            $io->text(\sprintf('<comment>%s</>   %s', str_pad($key, $width), $why));
        }

        $io->newLine();
        $io->text('settings --fix will:');
        $io->listing(array_map(static fn(SettingsRepairAction $action): string => $action->planned, $plan->actions));
        $io->text(\sprintf('Run: <info>%s settings --fix</info>', $binary));
        $io->newLine();
    }

    public static function done(SymfonyStyle $io, SettingsRepairPlan $plan): void
    {
        $io->text('Done:');
        $io->listing(array_map(static fn(SettingsRepairAction $action): string => $action->done, $plan->actions));
    }

    /**
     * The command as the player launched it — `pogo`, `./pogo.phar`, `bin/console` — so
     * that the line to type can be copied as it is.
     *
     * @param array<array-key, mixed> $server
     */
    public static function binary(array $server): string
    {
        $argv = \is_array($server['argv'] ?? null) ? $server['argv'] : [];

        return \is_string($argv[0] ?? null) && '' !== $argv[0] ? $argv[0] : 'pogo';
    }
}
