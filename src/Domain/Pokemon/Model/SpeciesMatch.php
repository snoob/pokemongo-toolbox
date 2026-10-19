<?php

declare(strict_types=1);

namespace App\Domain\Pokemon\Model;

/**
 * The species an identifier resolved to, and whether it was guessed: a typed name that
 * matched nothing taken for the nearest real one, which the answer has to own up to.
 */
final readonly class SpeciesMatch
{
    public function __construct(
        public Species $species,
        public bool $guessed = false,
    ) {}
}
