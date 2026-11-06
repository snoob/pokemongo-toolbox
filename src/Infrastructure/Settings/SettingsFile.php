<?php

declare(strict_types=1);

namespace App\Infrastructure\Settings;

use App\Infrastructure\Environment\UserDirectory;

/**
 * The player's defaults for command options, one object per command family:
 *
 *     {"pvp": {"floor": "5", "league": ["great", "ultra", "master"]},
 *      "inventory": {"account": "snoob2555"}}
 *
 * Under the XDG config directory, readable and editable by hand; `POGO_SETTINGS` points
 * elsewhere. A file that cannot be read is an error, never "no settings": the commands
 * would otherwise quietly run with other defaults — another account's inventory, for one.
 *
 * @phpstan-import-type Sections from SettingsDocument
 */
final class SettingsFile
{
    /** @var ?Sections */
    private ?array $loaded = null;

    /**
     * @param ?string $file null keeps it in the XDG config directory
     */
    public function __construct(
        private readonly ?string $file = null,
    ) {}

    public function path(): string
    {
        return $this->file ?? UserDirectory::config($_SERVER, 'POGO_SETTINGS', 'settings.json');
    }

    /**
     * @return Sections
     */
    public function load(): array
    {
        return $this->loaded ??= $this->read();
    }

    /**
     * @param Sections $sections a family left without options is dropped
     */
    public function save(array $sections): void
    {
        $sections = array_filter(
            $sections,
            /** @param array<string, string|list<string>> $options */
            static fn(array $options): bool => [] !== $options,
        );
        $file = $this->path();
        $directory = \dirname($file);

        if (!is_dir($directory) && !mkdir($directory, 0o775, true) && !is_dir($directory)) {
            throw new \RuntimeException(\sprintf('Cannot create "%s" to keep the settings.', $directory));
        }

        $temporary = $file . '.tmp';

        if (
            false === file_put_contents($temporary, SettingsDocument::encode($sections))
            || !rename($temporary, $file)
        ) {
            throw new \RuntimeException(\sprintf('Cannot write the settings to "%s".', $file));
        }

        $this->loaded = $sections;
    }

    /**
     * @return Sections
     */
    private function read(): array
    {
        $file = $this->path();

        if (!is_file($file)) {
            return [];
        }

        $json = file_get_contents($file);

        try {
            return SettingsDocument::decode(false === $json ? '' : $json);
        } catch (\UnexpectedValueException $e) {
            throw new \RuntimeException(
                \sprintf('The settings "%s" are unreadable (%s); fix or move the file.', $file, $e->getMessage()),
                previous: $e,
            );
        }
    }
}
