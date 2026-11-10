<?php

declare(strict_types=1);

namespace App\Domain\Pvp\Exception;

use App\Domain\Pokemon\Model\Species;
use App\Domain\Pvp\Model\League;

/**
 * A rank names no spread of that form in that league: past the last one, or the form
 * cannot get under the league's CP cap at all (`total` is then null).
 */
final class RankOutOfRange extends \DomainException
{
    public function __construct(
        public readonly Species $form,
        public readonly League $league,
        public readonly int $rank,
        public readonly ?int $total,
    ) {
        parent::__construct(
            null === $total
                ? \sprintf('%s cannot get under the %s CP cap.', $form->id->value, $league->label())
                : \sprintf('%s ranks %d spreads in the %s, not %d.', $form->id->value, $total, $league->label(), $rank),
        );
    }
}
