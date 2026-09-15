<?php

declare(strict_types=1);

namespace App\Infrastructure\Pvpoke;

use App\Domain\Pokemon\Model\MoveId;
use App\Domain\Pokemon\Model\SpeciesId;
use App\Domain\Pvp\Model\League;
use App\Domain\Pvp\Model\Moveset;
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
    private const string BASE_URL = 'https://raw.githubusercontent.com/pvpoke/pvpoke/master/src/data/rankings';

    /** @var array<string, array<string, array{position: int, total: int, score: float, moveset: list<string>}>> */
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

        return new SpeciesRank(
            $league,
            $entry['position'],
            $entry['total'],
            $entry['score'],
            $this->moveset($entry['moveset']),
        );
    }

    /**
     * The source lists the fast move first, then the charged moves it recommends —
     * three of them for the megas that carry an extra one.
     *
     * @param list<string> $moves
     */
    private function moveset(array $moves): ?Moveset
    {
        $ids = array_map(static fn(string $move): MoveId => new MoveId($move), $moves);
        $fast = array_shift($ids);

        return null === $fast || [] === $ids ? null : new Moveset($fast, array_values($ids));
    }

    /**
     * @return array<string, array{position: int, total: int, score: float, moveset: list<string>}>
     */
    private function index(League $league): array
    {
        $cached = $this->indexes[$league->value] ?? null;

        if (null !== $cached) {
            return $cached;
        }

        $entries = JsonValue::asArrayList($this->fetcher->fetch($this->urlFor($league)));
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
                'moveset' => JsonValue::asStringList($entry['moveset'] ?? null),
            ];
        }

        return $this->indexes[$league->value] = $index;
    }

    /**
     * The source splits its rankings by cup then by CP: the standard leagues live under
     * "all", the Mega Editions under "mega". Both naming schemes stop at this class.
     */
    private function urlFor(League $league): string
    {
        return \sprintf(
            '%s/%s/overall/rankings-%d.json',
            self::BASE_URL,
            $league->allowsMega() ? 'mega' : 'all',
            $league->cpCap() ?? 10_000,
        );
    }
}
