<?php

declare(strict_types=1);

namespace App\UI\Cli\Settings;

use App\Domain\Inventory\Model\Account;
use App\Domain\Pokemon\Model\IvFloor;
use App\UI\Cli\Input\CpOption;
use App\UI\Cli\Input\LeagueOption;
use Symfony\Component\Console\Input\InputOption;

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
     * Why a value cannot fill an option, null when it can: a list for an option taking one
     * value, or a value its parser refuses.
     *
     * @param string|list<string> $value
     */
    public static function refusal(InputOption $option, string|array $value): ?string
    {
        if (\is_array($value) && !$option->isArray()) {
            return \sprintf('--%s takes one value', $option->getName());
        }

        try {
            self::check($option->getName(), (array) $value);
        } catch (\InvalidArgumentException $e) {
            return $e->getMessage();
        }

        return null;
    }

    /**
     * A typed answer, checked as above, to save as it is. For an option taking one value
     * only: a repeatable one (`--league`) is a list, which `LeaguePicker` asks for.
     *
     * @throws \InvalidArgumentException naming what is wrong
     */
    public static function fromAnswer(string $option, string $answer): string
    {
        self::check($option, [$answer]);

        return $answer;
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
