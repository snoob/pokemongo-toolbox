<?php

declare(strict_types=1);

namespace App\Infrastructure\Build;

use App\Infrastructure\Json\JsonPath;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Build-time only: turns the game's species dump into the file the application reads
 * at runtime, plus the English form labels the translation catalogue needs.
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
     * @return array{species: array{generatedAt: string, species: list<array{id: string, dex: int, atk: int, def: int, sta: int, shadow: bool, base: bool, forms: list<string>}>}, formLabels: array<string, string>}
     */
    public function build(): array
    {
        $species = [];
        $formLabels = [];

        foreach ($this->rawEntries() as $entry) {
            $mapped = $this->mapper->map($entry);

            if (null === $mapped) {
                continue;
            }

            $species[] = $mapped;
            $formLabels = [...$formLabels, ...$this->formLabels->of($this->gameName($entry))];
        }

        return [
            'species' => ['generatedAt' => date(\DATE_ATOM), 'species' => $species],
            'formLabels' => $formLabels,
        ];
    }

    /**
     * @param array<array-key, mixed> $entry
     */
    private function gameName(array $entry): string
    {
        return \is_string($entry['speciesName'] ?? null) ? $entry['speciesName'] : '';
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    private function rawEntries(): array
    {
        return JsonPath::arrayListAt($this->http->request('GET', self::GAMEMASTER_URL)->toArray(), 'pokemon');
    }
}
