<?php

declare(strict_types=1);

namespace App\Infrastructure\Build;

use App\Infrastructure\Json\JsonValue;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Build-time only: the species names per locale, read in one pass from the Pokédex
 * name table rather than one HTTP call per species. Feeds the translation catalogues.
 */
final readonly class SpeciesNameCatalogueBuilder
{
    private const string URL = 'https://raw.githubusercontent.com/PokeAPI/pokeapi/master/data/v2/csv/pokemon_species_names.csv';

    /** Language ids used by the name table, keyed by the locale we publish. */
    private const array LANGUAGE_IDS = ['fr' => '5', 'en' => '9'];

    public function __construct(
        private HttpClientInterface $http,
    ) {}

    /**
     * @return array<string, array<int, string>> locale => (dex number => name)
     */
    public function build(): array
    {
        $rows = explode("\n", trim($this->http->request('GET', self::URL)->getContent()));
        array_shift($rows);

        $catalogues = array_fill_keys(array_keys(self::LANGUAGE_IDS), []);
        $localeOf = array_flip(self::LANGUAGE_IDS);

        foreach ($rows as $row) {
            $columns = str_getcsv($row, ',', '"', '\\');
            $locale = $localeOf[JsonValue::asString($columns[1] ?? null) ?? ''] ?? null;
            $dex = (int) JsonValue::asFloat($columns[0] ?? null);
            $name = JsonValue::asString($columns[2] ?? null);

            if (null === $locale || $dex < 1 || null === $name) {
                continue;
            }

            $catalogues[$locale][$dex] = $name;
        }

        return $catalogues;
    }
}
