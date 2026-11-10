<?php

declare(strict_types=1);

namespace App\UI\Cli\Settings;

use Symfony\Component\Console\Command\Command;

/**
 * Which family's setting fills an option of a command: its own family's, the global one
 * for the application's own settings, or another family's it borrows from.
 */
final readonly class SettingFamily
{
    /**
     * Options a command takes from another family's settings, by command then option
     * (decision 125): `inventory:check` weighs the trades of the account set up for
     * `pvp`, in the leagues played there — one answer in the wizard for both.
     */
    private const array BORROWED = [
        'app:inventory:check' => ['floor' => 'pvp', 'league' => 'pvp'],
    ];

    public static function of(Command $command, string $option, string $section): string
    {
        if (\in_array($option, ConfigurableOptions::APPLICATION_WIDE, true)) {
            return ConfigurableOptions::GLOBAL;
        }

        return self::BORROWED[(string) $command->getName()][$option] ?? $section;
    }

    /**
     * The settings that apply to a command, by option name.
     *
     * @param array<string, array<string, string|list<string>>> $sections as the settings file holds them
     *
     * @return array<string, string|list<string>>
     */
    public static function applicable(Command $command, string $section, array $sections): array
    {
        $applicable = [];

        foreach ($command->getDefinition()->getOptions() as $option) {
            $name = $option->getName();
            $value = $sections[self::of($command, $name, $section)][$name] ?? null;

            if (null !== $value) {
                $applicable[$name] = $value;
            }
        }

        return $applicable;
    }
}
