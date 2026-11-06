<?php

declare(strict_types=1);

namespace App\Domain\Stardust\Service;

use App\Domain\Stardust\Model\FedDefender;
use App\Domain\Stardust\Model\GymFeeding;
use App\Domain\Stardust\Model\Stardust;

/**
 * Stardust earned by feeding berries to the defenders of a gym.
 *
 * A berry pays 30 Stardust times its place in the streak, with no cap: 30, 60, … 300 for
 * the first defender. Switching quickly to the next defender keeps the streak, so the
 * second one goes on from 330 to 600, and every defender added pays more than the last.
 * A defender takes 10 berries at most.
 *
 * Provenance: the player's observations in the game, no upstream file — see specs/stardust.md.
 */
final readonly class BerryStardust
{
    public const int MAX_DEFENDERS = 6;
    public const int BERRIES_PER_DEFENDER = 10;

    private const int STREAK_STEP = 30;

    /**
     * Every defender of a full gym, so a smaller one reads off its first rows.
     */
    public function feedFullGym(): GymFeeding
    {
        $fed = [];
        $streak = 0;

        for ($position = 1; $position <= self::MAX_DEFENDERS; ++$position) {
            $stardust = Stardust::none();

            for ($berry = 1; $berry <= self::BERRIES_PER_DEFENDER; ++$berry) {
                ++$streak;
                $stardust = $stardust->plus(Stardust::reward(self::STREAK_STEP * $streak));
            }

            $fed[] = new FedDefender($position, self::BERRIES_PER_DEFENDER, $stardust);
        }

        return new GymFeeding($fed);
    }
}
