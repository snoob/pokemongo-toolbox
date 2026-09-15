<?php

declare(strict_types=1);

namespace App;

/**
 * Lists config files by reading the directory rather than by glob pattern.
 *
 * `glob()` returns nothing under the `phar://` stream wrapper, so the usual
 * `config/packages/*.yaml` import silently loads nothing inside the archive — the
 * application would boot with only the bundles' own defaults, and no warning.
 * `scandir()` does work there.
 */
final readonly class ConfigFiles
{
    public function __construct(
        private string $configDir,
    ) {}

    /**
     * @param string      $directory sub-directory of config/, empty for config/ itself
     * @param string|null $basename  keeps only files named "<basename>.<ext>"
     *
     * @return list<string>
     */
    public function in(string $directory, ?string $basename = null): array
    {
        $path = $this->configDir . $directory;
        $entries = is_dir($path) ? scandir($path) : false;

        if (false === $entries) {
            return [];
        }

        $files = [];

        foreach ($entries as $entry) {
            if (!$this->matches($entry, $basename)) {
                continue;
            }

            $files[] = $path . '/' . $entry;
        }

        sort($files);

        return $files;
    }

    private function matches(string $entry, ?string $basename): bool
    {
        if (null !== $basename && !str_starts_with($entry, $basename . '.')) {
            return false;
        }

        return 1 === preg_match('#\.(ya?ml|php)$#', $entry);
    }
}
