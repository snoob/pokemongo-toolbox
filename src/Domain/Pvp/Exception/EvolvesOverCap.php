<?php

declare(strict_types=1);

namespace App\Domain\Pvp\Exception;

use App\Domain\Pokemon\Model\Species;
use App\Domain\Pvp\Model\Cp;
use App\Domain\Pvp\Model\League;
use App\Domain\Pvp\Model\PokemonLevel;

/**
 * The Pokémon in hand is already powered up too far: evolving keeps the level, and at
 * that level the form it would fight as busts the league's CP cap.
 */
final class EvolvesOverCap extends \DomainException
{
    public function __construct(
        public readonly Cp $cp,
        public readonly PokemonLevel $level,
        public readonly Species $form,
        public readonly Cp $formCp,
        public readonly League $league,
    ) {
        parent::__construct(\sprintf(
            'At CP %d (level %s), %s would show %d CP, over the %s cap.',
            $cp->value,
            $level,
            $form->id->value,
            $formCp->value,
            $league->label(),
        ));
    }
}
