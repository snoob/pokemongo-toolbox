<?php

declare(strict_types=1);

namespace App\Domain\Pvp\Model;

use App\Domain\Pokemon\Model\IvSpread;

/**
 * Every IV spread of one species, ordered from best to worst for a league.
 */
final readonly class IvRanking
{
    /**
     * @param non-empty-list<RankedSpread> $spreads ordered best first
     */
    public function __construct(
        public League $league,
        /** @var non-empty-list<RankedSpread> */
        public array $spreads,
    ) {}

    public function best(): RankedSpread
    {
        return $this->spreads[0];
    }

    public function for(IvSpread $iv): RankedSpread
    {
        foreach ($this->spreads as $spread) {
            if ($spread->iv->equals($iv)) {
                return $spread;
            }
        }

        throw new \LogicException(\sprintf('Spread %s is missing from the ranking; the ranking is incomplete.', $iv));
    }

    /** @return list<RankedSpread> */
    public function top(int $count): array
    {
        return \array_slice($this->spreads, 0, max(0, $count));
    }
}
