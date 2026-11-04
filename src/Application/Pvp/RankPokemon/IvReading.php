<?php

declare(strict_types=1);

namespace App\Application\Pvp\RankPokemon;

use App\Domain\Pokemon\Model\IvSpread;
use App\Domain\Pvp\Model\IvDeduction;

/**
 * The spread to rank, and how the CP settled it when one was given.
 */
final readonly class IvReading
{
    public function __construct(
        public ?IvSpread $iv,
        public ?IvDeduction $deduction = null,
    ) {}
}
