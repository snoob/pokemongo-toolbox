<?php

declare(strict_types=1);

namespace App\Infrastructure\Inventory;

use App\Infrastructure\Json\JsonValue;

/**
 * Where the inventory is kept: the player's data, not a cache, so it follows the XDG
 * data directory rather than the cache one, and is the same file whether the tool runs
 * from the PHAR or from a checkout. `POGO_INVENTORY` points elsewhere.
 *
 * Read when the file is first needed, never at container build: a compiled container
 * must not freeze one user's home directory.
 */
final readonly class InventoryFile
{
    private const string DIRECTORY = 'pokemongo-toolbox';
    private const string NAME = 'inventory.json';

    /**
     * @param array<array-key, mixed> $server the process environment, as `$_SERVER` holds it
     */
    public static function path(array $server): string
    {
        $explicit = self::nonEmpty($server, 'POGO_INVENTORY');

        if (null !== $explicit) {
            return $explicit;
        }

        $xdg = self::nonEmpty($server, 'XDG_DATA_HOME');

        if (null !== $xdg) {
            return \sprintf('%s/%s/%s', $xdg, self::DIRECTORY, self::NAME);
        }

        $home = self::nonEmpty($server, 'HOME') ?? self::nonEmpty($server, 'USERPROFILE');

        if (null !== $home) {
            return \sprintf('%s/.local/share/%s/%s', $home, self::DIRECTORY, self::NAME);
        }

        throw new \RuntimeException('Cannot tell where to keep the inventory: set HOME or POGO_INVENTORY.');
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
