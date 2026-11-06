<?php

declare(strict_types=1);

namespace App\UI\Cli\Settings;

use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Settings as a two-column table, one row per "family.option".
 */
final readonly class SettingsTable
{
    /**
     * @param array<string, null|string|list<string>> $values by key; null shows as not set
     */
    public static function render(SymfonyStyle $io, array $values): void
    {
        $rows = [];

        foreach ($values as $key => $value) {
            $rows[] = [$key, null === $value ? '<fg=gray>not set</>' : implode(' ', (array) $value)];
        }

        $io->table(['Setting', 'Value'], $rows);
    }

    /**
     * @param array<string, array<string, string|list<string>>> $sections as the settings file holds them
     *
     * @return array<string, string|list<string>>
     */
    public static function flatten(array $sections): array
    {
        $values = [];

        foreach ($sections as $section => $options) {
            foreach ($options as $option => $value) {
                $values[$section . '.' . $option] = $value;
            }
        }

        return $values;
    }
}
