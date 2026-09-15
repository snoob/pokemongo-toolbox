<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use App\Domain\Pokemon\Model\DexNumber;
use App\Domain\Pvp\Model\MegaLevel;
use App\Domain\Pvp\Model\MegaLevelEffects;
use App\Domain\Pvp\Port\MegaLevelTable;

/**
 * The real shape of the game's table: nothing changes until the last Mega Level, which
 * hands the mega two extra Pokémon levels.
 */
final readonly class FixedMegaLevelTable implements MegaLevelTable
{
    /**
     * @param array<int, int> $additionalLevels
     */
    public function __construct(
        private array $additionalLevels = [4 => 2],
        private ?int $maxLevel = 4,
    ) {}

    #[\Override]
    public function effectsAt(MegaLevel $level): MegaLevelEffects
    {
        return new MegaLevelEffects(
            additionalLevels: $this->additionalLevels[$level->value] ?? 0,
            cooldownDays: [1 => 7, 2 => 5, 3 => 3, 4 => 1][$level->value] ?? 0,
            extraCandy: [1 => 1, 2 => 1, 3 => 2, 4 => 3][$level->value] ?? 0,
            movePowerMultiplier: [1 => 1.0, 2 => 1.1, 3 => 1.2, 4 => 1.3][$level->value] ?? 1.0,
        );
    }

    #[\Override]
    public function maxLevelFor(DexNumber $dex): ?MegaLevel
    {
        return null === $this->maxLevel ? null : new MegaLevel($this->maxLevel);
    }
}
