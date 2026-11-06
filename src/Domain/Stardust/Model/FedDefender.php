<?php

declare(strict_types=1);

namespace App\Domain\Stardust\Model;

final readonly class FedDefender
{
    /**
     * @param int $position 1 for the first defender fed
     */
    public function __construct(
        public int $position,
        public int $berries,
        public Stardust $stardust,
    ) {}
}
