<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use App\Domain\Pokemon\Model\SpeciesId;
use App\Domain\Pvp\Model\League;
use App\Domain\Pvp\Model\SpeciesRank;
use App\Domain\Pvp\Port\PvpRankingCatalog;

final readonly class InMemoryPvpRankingCatalog implements PvpRankingCatalog
{
    /**
     * @param array<string, array<string, SpeciesRank>> $ranks keyed by league then species id
     */
    public function __construct(
        private array $ranks = [],
    ) {}

    #[\Override]
    public function rankOf(SpeciesId $id, League $league): ?SpeciesRank
    {
        return $this->ranks[$league->value][$id->value] ?? null;
    }
}
