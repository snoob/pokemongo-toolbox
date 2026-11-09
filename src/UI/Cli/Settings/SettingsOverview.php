<?php

declare(strict_types=1);

namespace App\UI\Cli\Settings;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * What `settings` shows: the settings as a table, the file that keeps them, and a warning
 * for each value this version refuses. A key that fills no option is highlighted; what to
 * do about it is `SettingsRepairReport`'s.
 */
final readonly class SettingsOverview
{
    /**
     * @param array<string, array<string, string|list<string>>> $sections     as the settings file holds them
     * @param array<string, non-empty-list<InputOption>>       $configurable every key a setting can take
     * @param array<string, non-empty-list<InputOption>>       $listed       the keys to list even when not set (`--all`)
     *
     * @return bool whether a value was refused
     */
    public static function render(
        SymfonyStyle $io,
        array $sections,
        array $configurable,
        array $listed,
        string $path,
    ): bool {
        $rows = [];
        $problems = [];

        foreach (SettingsTable::flatten($sections) as $key => $value) {
            $known = \array_key_exists($key, $configurable);
            $problem = $known ? self::problem(explode('.', $key, 2)[1] ?? $key, (array) $value) : null;
            $rows[$key] = [
                $known && null === $problem ? $key : \sprintf('<comment>%s</>', $key),
                implode(' ', (array) $value),
                '',
            ];

            if (null !== $problem) {
                $problems[] = \sprintf('%s: %s', $key, $problem);
            }
        }

        foreach ($listed as $key => $options) {
            $rows[$key] = [$key, $rows[$key][1] ?? '', $options[0]->getDescription()];
        }

        ksort($rows);

        [] === $rows
            ? $io->text('Nothing set yet; "wizard" sets the usual defaults. "--all" lists what can be set.')
            : $io->table(['Setting', 'Value', [] === $listed ? '' : 'Option'], array_values($rows));

        $io->text(\sprintf('<fg=gray>Kept in %s</>', $path));

        if ([] !== $rows) {
            $io->text('<fg=gray>"wizard" sets them up again, question by question.</>');
        }
        $io->newLine();

        if ([] !== $problems) {
            $io->warning($problems);
        }

        return [] !== $problems;
    }

    /**
     * What is wrong with the value of a setting edited by hand, null when nothing is.
     *
     * @param list<string> $values
     */
    private static function problem(string $option, array $values): ?string
    {
        try {
            SettingValues::check($option, $values);
        } catch (\InvalidArgumentException $e) {
            return $e->getMessage();
        }

        return null;
    }
}
