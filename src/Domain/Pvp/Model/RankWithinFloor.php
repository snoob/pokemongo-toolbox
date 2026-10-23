<?php

declare(strict_types=1);

namespace App\Domain\Pvp\Model;

/**
 * Where a spread stands among the ones an IV floor leaves possible — "#3 of the 1331
 * spreads a Best Friend trade can roll" — as opposed to among all 4096.
 */
final readonly class RankWithinFloor
{
    public function __construct(
        public int $position,
        public int $total,
    ) {}
}
