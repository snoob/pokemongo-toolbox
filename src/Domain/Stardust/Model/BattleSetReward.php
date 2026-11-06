<?php

declare(strict_types=1);

namespace App\Domain\Stardust\Model;

final readonly class BattleSetReward
{
    public function __construct(
        public int $wins,
        public Stardust $stardust,
    ) {}
}
