<?php

declare(strict_types=1);

namespace App\Infrastructure\Build;

use App\Infrastructure\Json\JsonPath;
use App\Infrastructure\Json\JsonValue;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Build-time only: which types each in-game weather boosts, straight from the game files.
 */
final readonly class WeatherTableBuilder
{
    private const string GAME_MASTER_URL = 'https://raw.githubusercontent.com/PokeMiners/game_masters/master/latest/latest.json';
    private const string AFFINITY_PREFIX = 'WEATHER_AFFINITY_';
    private const string TYPE_PREFIX = 'POKEMON_TYPE_';

    public function __construct(
        private HttpClientInterface $http,
    ) {}

    /**
     * @return array{generatedAt: string, boostedTypes: array<string, list<string>>}
     */
    public function build(): array
    {
        $boosted = [];

        foreach (JsonValue::asArrayList($this->http->request('GET', self::GAME_MASTER_URL)->toArray()) as $template) {
            $id = JsonValue::asString($template['templateId'] ?? null) ?? '';
            $data = JsonPath::arrayAt($template, 'data');

            if (str_starts_with($id, self::AFFINITY_PREFIX)) {
                $affinity = JsonPath::arrayAt($data, 'weatherAffinities');
                $weather = JsonPath::stringAt($affinity, 'weatherCondition');

                if (null !== $weather) {
                    $boosted[strtolower($weather)] = $this->types(JsonPath::arrayAt($affinity, 'pokemonType'));
                }
            }
        }

        if ([] === $boosted) {
            throw new \RuntimeException('The game master no longer carries the weather affinities.');
        }

        ksort($boosted);

        return [
            'generatedAt' => date(\DATE_ATOM),
            'boostedTypes' => $boosted,
        ];
    }

    /**
     * "POKEMON_TYPE_FLYING" becomes "flying", the spelling the species file uses.
     *
     * @param array<array-key, mixed>|null $types
     *
     * @return list<string>
     */
    private function types(?array $types): array
    {
        $names = array_map(static fn(string $type): string => strtolower(substr(
            $type,
            \strlen(self::TYPE_PREFIX),
        )), JsonValue::asStringList($types));
        sort($names);

        return $names;
    }
}
