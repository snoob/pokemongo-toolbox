<?php

declare(strict_types=1);

namespace App\Domain\Pvp\Port;

use App\Domain\Pokemon\Model\DexNumber;
use App\Domain\Pvp\Model\MegaLevel;
use App\Domain\Pvp\Model\MegaLevelEffects;

interface MegaLevelTable
{
    public function effectsAt(MegaLevel $level): MegaLevelEffects;

    /**
     * The highest Mega Level this species can reach, or null when the game publishes no
     * progression for it.
     */
    public function maxLevelFor(DexNumber $dex): ?MegaLevel;
}
