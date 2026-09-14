<?php

declare(strict_types=1);

namespace App\Domain\Pvp\Port;

use App\Domain\Pvp\Model\PokemonLevel;

interface CpMultiplierTable
{
    /**
     * The CPM applied to base stats at a given level. Half levels are interpolated:
     * CPM(L + 0.5) = sqrt( (CPM(L)² + CPM(L+1)²) / 2 ).
     */
    public function multiplierFor(PokemonLevel $level): float;
}
