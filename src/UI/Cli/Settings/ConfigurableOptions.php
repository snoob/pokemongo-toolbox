<?php

declare(strict_types=1);

namespace App\UI\Cli\Settings;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;

/**
 * Which options a setting can give a default to. The key is the command family — the
 * middle part of `app:pvp:rank` — then the option's long name: `pvp.floor` is `--floor`
 * of every `app:pvp:*` command.
 *
 * Only options taking a value: a flag set in the settings could not be turned off on the
 * command line. The application's own options (`--env`, `--verbose`…) are left out too.
 */
final readonly class ConfigurableOptions
{
    /**
     * The families of the commands that write the settings rather than read them: they
     * must run whatever the file holds, to put it right.
     */
    private const array OWN_SECTIONS = ['settings', 'account'];

    /**
     * Options a setting never fills, whatever the command: the output format is chosen
     * per run — a JSON default would surprise whoever reads the terminal — and a CP
     * belongs to one Pokémon, never to all of them.
     */
    private const array NEVER = ['format', 'cp'];

    /**
     * The family of the settings that apply to every command having the option.
     */
    public const string GLOBAL = 'global';

    /**
     * Options set once for the whole application, under "global": the display language
     * and the game account are the same whatever the command. A family never sets them
     * on its own.
     */
    public const array APPLICATION_WIDE = ['locale', 'account'];

    /**
     * The family of a command, null when settings do not apply to it.
     */
    public static function section(Command $command): ?string
    {
        $parts = [];

        if (1 !== preg_match('/^app:([a-z0-9-]+):[a-z0-9-]+$/', (string) $command->getName(), $parts)) {
            return null;
        }

        $section = $parts[1] ?? null;

        return null === $section || \in_array($section, self::OWN_SECTIONS, true) ? null : $section;
    }

    /**
     * The option a setting can fill on this command, null when there is none.
     */
    public static function of(Command $command, string $name, ?Application $application): ?InputOption
    {
        $definition = $command->getDefinition();
        $global = $application?->getDefinition()->hasOption($name) ?? false;

        if ($global || \in_array($name, self::NEVER, true) || !$definition->hasOption($name)) {
            return null;
        }

        $option = $definition->getOption($name);

        return $option->acceptValue() ? $option : null;
    }

    /**
     * The options a setting fills, in every command of its family.
     *
     * @throws \InvalidArgumentException when it fills none, listing the settings that exist
     *
     * @return non-empty-list<InputOption>
     */
    public static function require(Application $application, SettingKey $key): array
    {
        $all = self::all($application);

        return $all[(string) $key] ?? throw new \InvalidArgumentException(\sprintf(
            'No option to set as "%s"; expected one of: %s.',
            $key,
            implode(', ', array_keys($all)),
        ));
    }

    /**
     * Every key a setting can take, each with the options it fills.
     *
     * @return array<string, non-empty-list<InputOption>> by "family.option", sorted
     */
    public static function all(Application $application): array
    {
        $keys = [];

        foreach ($application->all() as $name => $command) {
            $section = self::section($command);

            // An alias lists the same command a second time.
            if (null === $section || $name !== $command->getName()) {
                continue;
            }

            foreach (self::settable($command, $application) as $option) {
                $keys[self::familyOf($option->getName(), $section) . '.' . $option->getName()][] = $option;
            }
        }

        ksort($keys);

        return $keys;
    }

    /**
     * The keys of a settings file that fill no option: a typo, or a key an earlier version
     * read. Left alone they would be skipped without a word — an account setting under its
     * old name would send the inventory to "default".
     *
     * @param array<string, array<string, string|list<string>>> $sections as the settings file holds them
     *
     * @return list<string>
     */
    public static function unknown(Application $application, array $sections): array
    {
        return array_values(array_diff(
            array_keys(SettingsTable::flatten($sections)),
            array_keys(self::all($application)),
        ));
    }

    private static function familyOf(string $option, string $section): string
    {
        return \in_array($option, self::APPLICATION_WIDE, true) ? self::GLOBAL : $section;
    }

    /**
     * @return list<InputOption>
     */
    private static function settable(Command $command, Application $application): array
    {
        return array_values(array_filter(
            $command->getDefinition()->getOptions(),
            static fn(InputOption $option): bool => null !== self::of($command, $option->getName(), $application),
        ));
    }
}
