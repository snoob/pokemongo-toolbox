<?php

declare(strict_types=1);

namespace App\Application\Weather\ForecastIncense;

use App\Domain\Pokemon\Model\Species;
use App\Domain\Weather\Model\Place;

final readonly class ForecastIncenseResult
{
    /**
     * @param bool               $guessedPlace true when the place was inferred rather than typed
     * @param list<Species>       $targets
     * @param list<HourOutcome>   $hours        earliest first
     */
    public function __construct(
        public Place $place,
        public bool $guessedPlace,
        public array $targets,
        public array $hours,
    ) {}
}
