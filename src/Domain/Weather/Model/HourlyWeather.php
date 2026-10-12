<?php

declare(strict_types=1);

namespace App\Domain\Weather\Model;

/**
 * The weather expected in game for one hour. Null when the forecast describes a sky the
 * game has no known translation for: better an honest gap than a guess.
 */
final readonly class HourlyWeather
{
    public function __construct(
        public \DateTimeImmutable $hour,
        public ?GameWeather $weather,
    ) {}
}
