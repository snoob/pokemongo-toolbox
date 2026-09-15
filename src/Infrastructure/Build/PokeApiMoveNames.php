<?php

declare(strict_types=1);

namespace App\Infrastructure\Build;

use App\Infrastructure\Json\JsonValue;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Build-time only: the Pokédex move names in French, keyed by the slug the move table
 * uses (`shadow-claw`). The game's own move ids are matched against these slugs.
 */
final readonly class PokeApiMoveNames
{
    private const string MOVES_URL = 'https://raw.githubusercontent.com/PokeAPI/pokeapi/master/data/v2/csv/moves.csv';
    private const string MOVE_NAMES_URL = 'https://raw.githubusercontent.com/PokeAPI/pokeapi/master/data/v2/csv/move_names.csv';

    private const string FRENCH_LANGUAGE_ID = '5';

    public function __construct(
        private HttpClientInterface $http,
    ) {}

    /**
     * @return array<string, string> slug => French name
     */
    public function bySlug(): array
    {
        $slugs = [];

        foreach ($this->rows(self::MOVES_URL) as $columns) {
            $id = JsonValue::asString($columns[0] ?? null);
            $slug = JsonValue::asString($columns[1] ?? null);

            if (null !== $id && null !== $slug) {
                $slugs[$id] = $slug;
            }
        }

        return $this->namesFor($slugs);
    }

    /**
     * @param array<string, string> $slugs
     *
     * @return array<string, string>
     */
    private function namesFor(array $slugs): array
    {
        $names = [];

        foreach ($this->rows(self::MOVE_NAMES_URL) as $columns) {
            if (self::FRENCH_LANGUAGE_ID !== JsonValue::asString($columns[1] ?? null)) {
                continue;
            }

            $slug = $slugs[JsonValue::asString($columns[0] ?? null) ?? ''] ?? null;
            $name = JsonValue::asString($columns[2] ?? null);

            if (null !== $slug && null !== $name) {
                $names[$slug] = $name;
            }
        }

        return $names;
    }

    /**
     * @return iterable<array<array-key, mixed>>
     */
    private function rows(string $url): iterable
    {
        $rows = explode("\n", trim($this->http->request('GET', $url)->getContent()));
        array_shift($rows);

        foreach ($rows as $row) {
            yield str_getcsv($row, ',', '"', '\\');
        }
    }
}
