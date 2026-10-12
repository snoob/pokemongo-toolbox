<?php

declare(strict_types=1);

namespace App\Application\Weather\ForecastIncense;

use App\Domain\Pokemon\Model\SpeciesId;
use App\Domain\Weather\Model\GameWeather;

final readonly class HourOutcome
{
    /**
     * @param list<SpeciesId> $boosted the targets this weather boosts
     */
    public function __construct(
        public \DateTimeImmutable $hour,
        public ?GameWeather $weather,
        public array $boosted,
    ) {}

    /**
     * Whether the weather boosts at least one target: the hours worth a look.
     */
    public function boostsAny(): bool
    {
        return [] !== $this->boosted;
    }

    public function boosts(SpeciesId $id): bool
    {
        foreach ($this->boosted as $boosted) {
            if ($boosted->equals($id)) {
                return true;
            }
        }

        return false;
    }

    /**
     * An hour whose weather the forecast could not translate has no verdict.
     */
    public function isKnown(): bool
    {
        return null !== $this->weather;
    }
}
