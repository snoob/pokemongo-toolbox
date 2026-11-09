<?php

declare(strict_types=1);

namespace App\UI\Cli\Settings;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputOption;

/**
 * Works out how to put right a settings file holding keys that fill no option: a key
 * renamed by a version moves to its new name (`SettingMigrations`), any other is removed
 * with its value quoted, so that it can be set again. Shared by the error that blocks a
 * command, `settings` and `settings --fix`.
 */
final readonly class SettingsRepair
{
    /**
     * @param array<string, array{to: ?string, since: string}> $migrations by old key
     */
    public function __construct(
        private array $migrations = SettingMigrations::ALL,
    ) {}

    /**
     * @param array<string, array<string, string|list<string>>> $sections as the settings file holds them
     */
    public function plan(Application $application, array $sections): SettingsRepairPlan
    {
        $known = ConfigurableOptions::all($application);
        $problems = [];
        $actions = [];
        $fixed = $sections;

        foreach (SettingsTable::flatten($sections) as $key => $value) {
            if (\array_key_exists($key, $known)) {
                continue;
            }

            [$section, $option] = self::split($key);
            unset($fixed[$section][$option]);
            $migration = $this->migrations[$key] ?? null;
            $to = $migration['to'] ?? null;

            if (null === $migration || null === $to || !\array_key_exists($to, $known)) {
                $problems[$key] = null === $migration ? 'no option has this name' : 'removed in ' . $migration['since'];
                $actions[] = SettingsRepairAction::lose(
                    $key,
                    $value,
                    null === $migration ? 'set it again under its right name' : 'the option no longer exists',
                );

                continue;
            }

            $problems[$key] = \sprintf('renamed %s in %s', $to, $migration['since']);
            array_push($actions, ...self::move($key, $value, $to, $known[$to], $fixed));
        }

        return new SettingsRepairPlan(
            $problems,
            $actions,
            array_filter(
                $fixed,
                /** @param array<string, string|list<string>> $options */
                static fn(array $options): bool => [] !== $options,
            ),
        );
    }

    /**
     * @param string|list<string>                               $value
     * @param non-empty-list<InputOption>                       $options the new key fills
     * @param array<string, array<string, string|list<string>>> $fixed   updated when the value moves
     *
     * @return list<SettingsRepairAction>
     */
    private static function move(string $key, string|array $value, string $to, array $options, array &$fixed): array
    {
        [$section, $option] = self::split($to);
        $current = $fixed[$section][$option] ?? null;

        if (null !== $current) {
            return [SettingsRepairAction::keep($to, $current, $key, $value)];
        }

        $refused = SettingValues::refusal($options[0], $value);

        if (null !== $refused) {
            return [SettingsRepairAction::lose($key, $value, \sprintf('%s refuses it: %s', $to, $refused))];
        }

        $fixed[$section][$option] = $value;

        return SettingsRepairAction::move($key, $value, $to);
    }

    /**
     * @return array{string, string}
     */
    private static function split(string $key): array
    {
        $parts = explode('.', $key, 2);

        return [$parts[0], $parts[1] ?? ''];
    }
}
