<?php

declare(strict_types=1);

namespace App\Infrastructure\Environment;

use App\Infrastructure\Json\JsonValue;

/**
 * Where the player's own files go, after the XDG base directories: data under
 * `$XDG_DATA_HOME` (`~/.local/share`), settings under `$XDG_CONFIG_HOME` (`~/.config`).
 * The same place whether the tool runs from the PHAR or from a checkout; an environment
 * variable named by the caller points elsewhere.
 *
 * Read when the path is first needed, never at container build: a compiled container
 * must not freeze one user's home directory.
 */
final readonly class UserDirectory
{
    private const string APPLICATION = 'pokemongo-toolbox';

    /**
     * @param array<array-key, mixed> $server   the process environment, as `$_SERVER` holds it
     * @param string                  $override the variable that replaces the whole path
     * @param string                  $name     the file or directory under the application's one
     */
    public static function data(array $server, string $override, string $name): string
    {
        return self::resolve($server, $override, $name, 'XDG_DATA_HOME', '.local/share');
    }

    /**
     * @param array<array-key, mixed> $server
     */
    public static function config(array $server, string $override, string $name): string
    {
        return self::resolve($server, $override, $name, 'XDG_CONFIG_HOME', '.config');
    }

    /**
     * @param array<array-key, mixed> $server
     */
    private static function resolve(array $server, string $override, string $name, string $xdg, string $inHome): string
    {
        $explicit = self::nonEmpty($server, $override);

        if (null !== $explicit) {
            return $explicit;
        }

        $base = self::nonEmpty($server, $xdg);

        if (null !== $base) {
            return \sprintf('%s/%s/%s', $base, self::APPLICATION, $name);
        }

        $home = self::nonEmpty($server, 'HOME') ?? self::nonEmpty($server, 'USERPROFILE');

        if (null !== $home) {
            return \sprintf('%s/%s/%s/%s', $home, $inHome, self::APPLICATION, $name);
        }

        throw new \RuntimeException(\sprintf('Cannot tell where to keep "%s": set HOME or %s.', $name, $override));
    }

    /**
     * @param array<array-key, mixed> $server
     */
    private static function nonEmpty(array $server, string $name): ?string
    {
        $value = JsonValue::asString($server[$name] ?? null);

        if (null === $value) {
            $environment = getenv($name);
            $value = false === $environment ? null : $environment;
        }

        return '' === $value ? null : $value;
    }
}
