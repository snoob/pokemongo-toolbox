<?php

declare(strict_types=1);

namespace App\UI\Cli\Settings;

/**
 * One step of a `settings --fix`, told before it is done and after.
 */
final readonly class SettingsRepairAction
{
    public function __construct(
        public string $planned,
        public string $done,
    ) {}

    /**
     * A key removed, its value quoted so that it can be set again.
     *
     * @param string|list<string> $value
     */
    public static function lose(string $key, string|array $value, string $why): self
    {
        $what = \sprintf('%s — its value "%s" is lost; %s', $key, self::shown($value), $why);

        return new self('remove ' . $what, 'removed ' . $what);
    }

    /**
     * A renamed key whose new name is already set: the new one wins.
     *
     * @param string|list<string> $kept
     * @param string|list<string> $dropped
     */
    public static function keep(string $to, string|array $kept, string $key, string|array $dropped): self
    {
        $kept = self::shown($kept);
        $dropped = self::shown($dropped);

        return new self(
            \sprintf('keep %s = %s, drop %s = %s', $to, $kept, $key, $dropped),
            \sprintf('kept %s = %s, dropped %s = %s', $to, $kept, $key, $dropped),
        );
    }

    /**
     * A renamed key, its value moved to the new name.
     *
     * @param string|list<string> $value
     *
     * @return list<self>
     */
    public static function move(string $key, string|array $value, string $to): array
    {
        $set = \sprintf('set %s to %s, the value of %s', $to, self::shown($value), $key);

        return [new self($set, $set), new self('remove ' . $key, 'removed ' . $key)];
    }

    /**
     * @param string|list<string> $value
     */
    private static function shown(string|array $value): string
    {
        return implode(' ', (array) $value);
    }
}
