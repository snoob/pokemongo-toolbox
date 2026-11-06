<?php

declare(strict_types=1);

namespace App\Domain\Stardust\Model;

final readonly class CatchReward
{
    public function __construct(
        public int $catches,
        public Stardust $stardust,
        public Stardust $weatherBoosted,
    ) {}
}
