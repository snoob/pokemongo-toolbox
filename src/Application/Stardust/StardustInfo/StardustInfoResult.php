<?php

declare(strict_types=1);

namespace App\Application\Stardust\StardustInfo;

use App\Domain\Stardust\Model\BattleSetReward;
use App\Domain\Stardust\Model\CatchReward;
use App\Domain\Stardust\Model\GymFeeding;
use App\Domain\Stardust\Model\SeasonReward;

final readonly class StardustInfoResult
{
    /**
     * @param non-empty-list<CatchReward>     $catches
     * @param non-empty-list<BattleSetReward> $battleSets
     * @param non-empty-list<SeasonReward>    $seasonEnd
     * @param GymFeeding                      $gym        a full gym, defender by defender
     */
    public function __construct(
        public array $catches,
        public array $battleSets,
        public array $seasonEnd,
        public GymFeeding $gym,
    ) {}
}
