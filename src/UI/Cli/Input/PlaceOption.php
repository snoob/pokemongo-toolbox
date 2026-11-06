<?php

declare(strict_types=1);

namespace App\UI\Cli\Input;

/**
 * Reads `--at`: a place the player names, or "auto" for where their IP address says they
 * are. "auto" is the option's default, so that it shows in the help, and stays typable
 * to override a "weather.at" setting when away from home.
 */
final readonly class PlaceOption
{
    public const string AUTO = 'auto';

    /**
     * @return ?string the place to look up; null to guess it from the IP address
     */
    public static function parse(?string $value): ?string
    {
        $value = trim($value ?? '');

        return '' === $value || self::isAuto($value) ? null : $value;
    }

    public static function isAuto(string $value): bool
    {
        return self::AUTO === strtolower(trim($value));
    }
}
