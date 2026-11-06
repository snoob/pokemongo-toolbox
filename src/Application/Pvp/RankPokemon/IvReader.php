<?php

declare(strict_types=1);

namespace App\Application\Pvp\RankPokemon;

use App\Domain\Pokemon\Model\Species;
use App\Domain\Pvp\Exception\IvMismatch;
use App\Domain\Pvp\Service\IvDeducer;

/**
 * Turns the IVs as typed into the spread to rank, holding them against the CP the game
 * shows when one was given.
 */
final readonly class IvReader
{
    public function __construct(
        private IvDeducer $deducer,
    ) {}

    /**
     * The CP shown is the form in hand's, so that is the one it is checked against.
     *
     * @throws IvMismatch when the CP does not settle the IVs
     */
    public function read(Species $inHand, RankPokemonQuery $query): IvReading
    {
        if (null === $query->iv || null === $query->cp) {
            return new IvReading($query->iv?->toSpread());
        }

        $deduction = $this->deducer->deduce($inHand->baseStats, $query->iv, $query->cp, $query->effectiveLevelCap());

        return new IvReading($deduction->iv, $deduction);
    }
}
