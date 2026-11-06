<?php

declare(strict_types=1);

namespace App\Domain\Stardust\Model;

final readonly class SeasonReward
{
    /**
     * @param non-empty-string $rank as the game names it: 20, Ace, Veteran, Expert, Legend
     */
    public function __construct(
        public string $rank,
        public Stardust $stardust,
    ) {}
}
