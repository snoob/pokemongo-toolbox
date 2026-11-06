<?php

declare(strict_types=1);

namespace App\Domain\Stardust\Service;

use App\Domain\Stardust\Model\CatchReward;
use App\Domain\Stardust\Model\Stardust;

/**
 * Stardust earned by catching Pokémon: 100 a catch, a quarter more when the weather boosts
 * the species. Evolved forms caught in the wild pay more and are left out.
 *
 * Provenance: the game's long-standing rule, no upstream file — see GUIDELINES §4 quater.
 * The Star Piece applies to each catch on its own, so a boosted one pays 187, not 187.5.
 */
final readonly class CatchStardust
{
    /** @var non-empty-list<int> */
    public const array CATCH_COUNTS = [1, 10, 50, 100];

    private const int PER_CATCH = 100;
    private const int PER_BOOSTED_CATCH = 125;

    /**
     * @return non-empty-list<CatchReward>
     */
    public function rewards(): array
    {
        return array_map(
            static fn(int $catches): CatchReward => new CatchReward(
                $catches,
                Stardust::reward(self::PER_CATCH)->times($catches),
                Stardust::reward(self::PER_BOOSTED_CATCH)->times($catches),
            ),
            self::CATCH_COUNTS,
        );
    }
}
