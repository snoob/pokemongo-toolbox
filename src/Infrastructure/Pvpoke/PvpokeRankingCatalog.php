<?php

declare(strict_types=1);

namespace App\Infrastructure\Pvpoke;

use App\Domain\Pokemon\Model\SpeciesId;
use App\Domain\Pvp\Model\League;
use App\Domain\Pvp\Model\SpeciesRank;
use App\Domain\Pvp\Port\PvpRankingCatalog;
use App\Infrastructure\Json\JsonFetcher;
use App\Infrastructure\Json\JsonValue;

/**
 * Overall league rankings, straight from the source that publishes them as JSON.
 * The file naming (1500/2500/10000) is this provider's convention and stops here.
 */
final class PvpokeRankingCatalog implements PvpRankingCatalog
{
    private const string BASE_URL = 'https://raw.githubusercontent.com/pvpoke/pvpoke/master/src/data/rankings/all/overall';

    /** @var array<string, array<string, array{position: int, total: int, score: float}>> */
    private array $indexes = [];

    public function __construct(
        private readonly JsonFetcher $fetcher,
    ) {}

    #[\Override]
    public function rankOf(SpeciesId $id, League $league): ?SpeciesRank
    {
        $index = $this->index($league);
        $entry = $index[$id->value] ?? null;

        if (null === $entry) {
            return null;
        }

        return new SpeciesRank($league, $entry['position'], $entry['total'], $entry['score']);
    }

    /**
     * @return array<string, array{position: int, total: int, score: float}>
     */
    private function index(League $league): array
    {
        $cached = $this->indexes[$league->value] ?? null;

        if (null !== $cached) {
            return $cached;
        }

        $entries = JsonValue::asArrayList($this->fetcher->fetch(\sprintf(
            '%s/rankings-%d.json',
            self::BASE_URL,
            $this->fileFor($league),
        )));
        $total = \count($entries);
        $index = [];
        $position = 0;

        foreach ($entries as $entry) {
            ++$position;
            $id = JsonValue::asString($entry['speciesId'] ?? null);

            if (null === $id) {
                continue;
            }

            $index[$id] = [
                'position' => $position,
                'total' => $total,
                'score' => JsonValue::asFloat($entry['score'] ?? null),
            ];
        }

        return $this->indexes[$league->value] = $index;
    }

    private function fileFor(League $league): int
    {
        return match ($league) {
            League::Great => 1500,
            League::Ultra => 2500,
            League::Master => 10_000,
        };
    }
}
