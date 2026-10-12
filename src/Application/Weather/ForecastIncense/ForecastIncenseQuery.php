<?php

declare(strict_types=1);

namespace App\Application\Weather\ForecastIncense;

final readonly class ForecastIncenseQuery
{
    /**
     * @param string|null $at a place typed by the player; null to guess where they are
     */
    public function __construct(
        public ?string $at = null,
    ) {}
}
