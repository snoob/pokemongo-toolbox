<?php

declare(strict_types=1);

namespace App\Domain\Pvp\Model;

use App\Domain\Pokemon\Model\IvFloor;
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

    /**
     * The best spread the floor leaves possible, still placed among all 4096: that
     * position is what the floor costs. Null when every spread above the floor busts the
     * CP cap even at level 1.
     */
    public function bestFrom(IvFloor $floor): ?RankedSpread
    {
        foreach ($this->spreads as $spread) {
            if ($floor->allows($spread->iv)) {
                return $spread;
            }
        }

        return null;
    }

    public function withinFloor(IvSpread $iv, IvFloor $floor): RankWithinFloor
    {
        $position = null;
        $total = 0;

        foreach ($this->spreads as $spread) {
            if (!$floor->allows($spread->iv)) {
                continue;
            }

            ++$total;

            if ($spread->iv->equals($iv)) {
                $position = $total;
            }
        }

        if (null === $position) {
            throw new \LogicException(\sprintf('Spread %s is not ranked above the floor of %s.', $iv, $floor));
        }

        return new RankWithinFloor($position, $total);
    }

    /** @return list<RankedSpread> */
    public function top(int $count): array
    {
        return \array_slice($this->spreads, 0, max(0, $count));
    }
}
