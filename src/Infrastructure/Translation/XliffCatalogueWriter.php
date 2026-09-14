<?php

declare(strict_types=1);

namespace App\Infrastructure\Translation;

use Symfony\Component\Translation\Dumper\XliffFileDumper;
use Symfony\Component\Translation\MessageCatalogue;

/**
 * Writes a generated translation catalogue.
 *
 * The XLIFF is produced by the Translation component's own dumper rather than by hand:
 * escaping, structure and file naming are its job, and the loader that reads the result
 * ships with it (GUIDELINES.md §8).
 */
final readonly class XliffCatalogueWriter
{
    public function __construct(
        private XliffFileDumper $dumper,
    ) {}

    /**
     * @param array<array-key, string> $messages key => translation
     */
    public function write(
        string $directory,
        string $domain,
        string $locale,
        array $messages,
        string $sourceLocale = 'en',
    ): void {
        $keyed = [];

        foreach ($messages as $key => $translation) {
            $keyed[(string) $key] = $translation;
        }

        $this->dumper->dump(new MessageCatalogue($locale, [$domain => $keyed]), [
            'path' => $directory,
            'default_locale' => $sourceLocale,
            'xliff_version' => '1.2',
        ]);
    }
}
