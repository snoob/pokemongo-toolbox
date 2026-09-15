<?php

declare(strict_types=1);

namespace App\Infrastructure\Build;

use App\Infrastructure\Json\JsonPath;
use App\Infrastructure\Json\JsonValue;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Build-time only: turns the game's species dump into the file the application reads
 * at runtime, plus the English labels the translation catalogues need.
 */
final readonly class SpeciesCatalogBuilder
{
    private const string GAMEMASTER_URL = 'https://raw.githubusercontent.com/pvpoke/pvpoke/master/src/data/gamemaster.json';

    public function __construct(
        private HttpClientInterface $http,
        private GameMasterEntryMapper $mapper,
        private FormLabels $formLabels,
    ) {}

    /**
     * @return array{species: array{generatedAt: string, species: list<array{id: string, dex: int, atk: int, def: int, sta: int, shadow: bool, super: bool, base: bool, forms: list<string>, elite: list<string>}>}, formLabels: array<string, string>, moves: list<array{id: string, name: string, power: int, energy: int, mega: bool}>}
     */
    public function build(): array
    {
        $gamemaster = $this->http->request('GET', self::GAMEMASTER_URL)->toArray();
        $species = [];
        $formLabels = [];

        foreach (JsonPath::arrayListAt($gamemaster, 'pokemon') as $entry) {
            $mapped = $this->mapper->map($entry);

            if (null === $mapped) {
                continue;
            }

            $species[] = $mapped;
            $formLabels = [
                ...$formLabels,
                ...$this->formLabels->of(JsonValue::asString($entry['speciesName'] ?? null) ?? ''),
            ];
        }

        return [
            'species' => ['generatedAt' => date(\DATE_ATOM), 'species' => $species],
            'formLabels' => $formLabels,
            'moves' => $this->moves($gamemaster),
        ];
    }

    /**
     * @return list<array{id: string, name: string, power: int, energy: int, mega: bool}>
     */
    private function moves(mixed $gamemaster): array
    {
        $moves = [];

        foreach (JsonPath::arrayListAt($gamemaster, 'moves') as $move) {
            $id = JsonValue::asString($move['moveId'] ?? null);
            $name = JsonValue::asString($move['name'] ?? null);

            if (null !== $id && null !== $name) {
                $moves[] = [
                    'id' => $id,
                    'name' => $name,
                    'power' => (int) JsonValue::asFloat($move['power'] ?? null),
                    'energy' => (int) JsonValue::asFloat($move['energy'] ?? null),
                    // The game flags the extra Charged Attack a Super Mega carries.
                    'mega' => true === ($move['isMegaMove'] ?? false),
                ];
            }
        }

        return $moves;
    }
}
