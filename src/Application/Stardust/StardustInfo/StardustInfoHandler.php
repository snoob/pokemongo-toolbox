<?php

declare(strict_types=1);

namespace App\Application\Stardust\StardustInfo;

use App\Domain\Stardust\Service\BattleLeagueStardust;
use App\Domain\Stardust\Service\BerryStardust;
use App\Domain\Stardust\Service\CatchStardust;

/**
 * Takes no query: the answer is the same table for every player.
 */
final readonly class StardustInfoHandler
{
    public function __construct(
        private CatchStardust $catches,
        private BattleLeagueStardust $battleLeague,
        private BerryStardust $berries,
    ) {}

    public function __invoke(): StardustInfoResult
    {
        return new StardustInfoResult(
            $this->catches->rewards(),
            $this->battleLeague->sets(),
            $this->battleLeague->seasonEnd(),
            $this->berries->feedFullGym(),
        );
    }
}
