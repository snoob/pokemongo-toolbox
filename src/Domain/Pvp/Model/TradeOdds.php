<?php

declare(strict_types=1);

namespace App\Domain\Pvp\Model;

/**
 * What a trade could make of a spread: of the `total` spreads the floor leaves possible
 * under the league's cap, `beatenBy` have a strictly higher stat product. A trade rolls
 * each IV evenly from the floor to 15, so every one of them is as likely, and
 * `beatenBy / total` is the chance a trade does better. `best` is the best of them.
 *
 * Zero beaten means no trade from that source is worth making, wherever the spread came
 * from: a wild catch under the floor can be done with too.
 */
final readonly class TradeOdds
{
    public function __construct(
        public int $beatenBy,
        public int $total,
        public RankedSpread $best,
    ) {}

    public function canBeBeaten(): bool
    {
        return $this->beatenBy > 0;
    }

    /**
     * The chance one trade beats the spread, from 0 to 1.
     */
    public function chance(): float
    {
        return $this->beatenBy / $this->total;
    }
}
