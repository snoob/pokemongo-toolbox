<?php

declare(strict_types=1);

namespace App\Infrastructure\Json;

use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * A JSON document the player keeps and may edit by hand — the inventory, the settings.
 *
 * Unlike a cache, a file that cannot be read is an error, never an empty document: the
 * next save would otherwise wipe what the player kept. A save writes a temporary file
 * next to it and renames it, so an interrupted run leaves the previous file whole.
 */
final readonly class JsonDocumentFile
{
    private const int DIRECTORY_MODE = 0o775;

    /**
     * @template T
     *
     * @param string                $what   named in errors, e.g. "inventory"
     * @param \Closure(string): T   $decode throws \UnexpectedValueException naming what is wrong
     *
     * @return ?T null when the file does not exist yet
     */
    public static function read(string $file, string $what, \Closure $decode): mixed
    {
        if (!is_file($file)) {
            return null;
        }

        try {
            return $decode(new Filesystem()->readFile($file));
        } catch (\UnexpectedValueException|IOException $e) {
            throw new \RuntimeException(
                \sprintf(
                    'The %s "%s" is unreadable (%s); fix or move it, nothing was changed.',
                    $what,
                    $file,
                    $e->getMessage(),
                ),
                previous: $e,
            );
        }
    }

    /**
     * Follows a symbolic link rather than replacing it: a file kept in a dotfiles
     * repository stays there. A missing directory is created writable by its owner and
     * group only, whatever the umask allows.
     */
    public static function write(string $file, string $what, string $json): void
    {
        $filesystem = new Filesystem();

        try {
            $filesystem->mkdir(\dirname($file), self::DIRECTORY_MODE);
            $filesystem->dumpFile($file, $json);
        } catch (IOException $e) {
            throw new \RuntimeException(
                \sprintf('Cannot write the %s to "%s" (%s).', $what, $file, $e->getMessage()),
                previous: $e,
            );
        }
    }
}
