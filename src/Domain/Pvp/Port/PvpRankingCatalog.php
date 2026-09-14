<?php

declare(strict_types=1);

namespace App\Domain\Pvp\Port;

use App\Domain\Pokemon\Model\SpeciesId;
use App\Domain\Pvp\Model\League;
use App\Domain\Pvp\Model\SpeciesRank;

interface PvpRankingCatalog
{
    /**
     * Null when the species is not ranked in that league (ineligible, or absent
     * from the published ranking).
     */
    public function rankOf(SpeciesId $id, League $league): ?SpeciesRank;
}
