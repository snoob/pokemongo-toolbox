<?php

declare(strict_types=1);

namespace App\UI\Cli\Settings;

use Symfony\Component\Console\Application;

/**
 * A key that fills no option would be skipped without a word, and the command would run
 * on a default the player believes changed — the inventory of another account.
 */
final readonly class UnknownSettings
{
    /**
     * @param array<string, array<string, string|list<string>>> $sections as the settings file holds them
     *
     * @return ?string what to tell, null when every key fills an option
     */
    public static function message(?Application $application, array $sections, string $path): ?string
    {
        $unknown = null === $application ? [] : ConfigurableOptions::unknown($application, $sections);

        if ([] === $unknown) {
            return null;
        }

        $one = 1 === \count($unknown);

        return \sprintf(
            '%s "%s" in %s %s no option: remove %s from the file ("settings" shows what is wrong).',
            $one ? 'The setting' : 'The settings',
            implode('", "', $unknown),
            $path,
            $one ? 'sets' : 'set',
            $one ? 'it' : 'them',
        );
    }
}
