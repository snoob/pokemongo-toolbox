<?php

declare(strict_types=1);

namespace App\Domain\Pvp\Exception;

use App\Domain\Pvp\Model\League;

/**
 * Thrown when no IV spread of a species can stay under the league's CP cap —
 * the species simply cannot be entered, whatever its IVs.
 */
final class SpeciesIneligibleForLeague extends \RuntimeException
{
    public function __construct(
        public readonly League $league,
    ) {
        parent::__construct(\sprintf('No IV spread stays under the %s CP cap.', $league->label()));
    }
}
