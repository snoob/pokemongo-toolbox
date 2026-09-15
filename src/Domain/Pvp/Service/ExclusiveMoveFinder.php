<?php

declare(strict_types=1);

namespace App\Domain\Pvp\Service;

use App\Domain\Pokemon\Model\Move;
use App\Domain\Pokemon\Port\MoveCatalog;
use App\Domain\Pvp\Model\Moveset;

/**
 * Picks out the extra Charged Attack a Super Mega carries.
 *
 * The game flags the move itself, so it is recognised by that flag rather than by the
 * "+" its name happens to end with.
 */
final readonly class ExclusiveMoveFinder
{
    public function __construct(
        private MoveCatalog $moves,
    ) {}

    /**
     * @param list<Moveset> $movesets
     */
    public function in(array $movesets): ?Move
    {
        foreach ($movesets as $moveset) {
            foreach ($moveset->charged as $moveId) {
                $move = $this->moves->find($moveId);

                if (null !== $move && $move->megaExclusive) {
                    return $move;
                }
            }
        }

        return null;
    }
}
