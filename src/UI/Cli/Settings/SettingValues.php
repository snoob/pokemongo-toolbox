<?php

declare(strict_types=1);

namespace App\UI\Cli\Settings;

use App\Domain\Inventory\Model\Account;
use App\Domain\Pokemon\Model\IvFloor;
use App\Infrastructure\Json\JsonValue;
use App\UI\Cli\Input\CpOption;
use App\UI\Cli\Input\LeagueOption;

/**
 * Checks a setting before it is saved, with the parser the commands use for that option,
 * so that a typo is caught by the wizard or `settings:set` rather than by the next command. An option
 * is known by its long name, whatever its family: `--floor` means the same everywhere.
 *
 * An option with no check here — `--at`, free text — is taken as it is.
 */
final readonly class SettingValues
{
    private const array FORMATS = ['text', 'json'];
    private const array LOCALES = ['fr', 'en'];

    /**
     * @param list<string> $values
     *
     * @throws \InvalidArgumentException naming what is wrong
     */
    public static function check(string $option, array $values): void
    {
        foreach ($values as $value) {
            match ($option) {
                'account' => new Account($value),
                'floor' => IvFloor::fromString($value),
                'league' => LeagueOption::parse([$value]),
                'cp' => CpOption::parse($value),
                'format' => self::oneOf($option, $value, self::FORMATS),
                'locale' => self::oneOf($option, $value, self::LOCALES),
                default => null,
            };
        }
    }

    /**
     * A typed answer as the setting is saved: leagues split on spaces or commas
     * ("great ultra", "great, ultra"), anything else as one value. Checked as above.
     *
     * @throws \InvalidArgumentException naming what is wrong
     *
     * @return string|list<string>
     */
    public static function fromAnswer(string $option, string $answer): string|array
    {
        if ('league' !== $option) {
            self::check($option, [$answer]);

            return $answer;
        }

        $leagues = array_values(array_filter(
            JsonValue::asStringList(preg_split('/[\s,]+/', strtolower($answer))),
            static fn(string $league): bool => '' !== $league,
        ));
        self::check($option, $leagues);

        return $leagues;
    }

    /**
     * @param list<string> $allowed
     */
    private static function oneOf(string $option, string $value, array $allowed): void
    {
        if (!\in_array(strtolower($value), $allowed, true)) {
            throw new \InvalidArgumentException(\sprintf(
                'Unknown %s "%s"; expected %s.',
                $option,
                $value,
                implode(' or ', $allowed),
            ));
        }
    }
}
