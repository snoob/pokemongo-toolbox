<?php

declare(strict_types=1);

namespace App\Domain\Pvp\Model;

use App\Domain\Pokemon\Model\IvSpread;

/**
 * A spread that gives the CP read in the game, and the levels at which it does. Usually
 * one level; two neighbours can share a low CP.
 *
 * `guessed` is false when every IV was typed: the CP only confirmed them.
 */
final readonly class IvDeduction
{
    /**
     * @param non-empty-list<PokemonLevel> $levels lowest first
     */
    public function __construct(
        public IvSpread $iv,
        public Cp $cp,
        public array $levels,
        public bool $guessed,
    ) {}
}
