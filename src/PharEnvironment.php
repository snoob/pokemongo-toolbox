<?php

declare(strict_types=1);

namespace App;

/**
 * Tells whether the application runs from a PHAR, and where it may write.
 *
 * A PHAR is read-only, so the compiled container, the logs and the rankings cache all
 * have to live outside it.
 */
final readonly class PharEnvironment
{
    private const string DIRECTORY = 'pokemongo-toolbox';

    public static function isRunning(): bool
    {
        return '' !== \Phar::running(false);
    }

    /**
     * The archive itself, which is also the project root once packaged.
     */
    public static function root(): string
    {
        return \Phar::running();
    }

    /**
     * Follows the XDG base directory spec, so files land where the system expects them
     * rather than beside the binary.
     */
    public static function writableDir(): string
    {
        $xdg = $_SERVER['XDG_CACHE_HOME'] ?? null;

        if (\is_string($xdg) && '' !== $xdg) {
            return $xdg . '/' . self::DIRECTORY;
        }

        $home = $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? null;

        if (\is_string($home) && '' !== $home) {
            return $home . '/.cache/' . self::DIRECTORY;
        }

        return sys_get_temp_dir() . '/' . self::DIRECTORY;
    }
}
