<?php

declare(strict_types=1);

namespace App\Domain\Encounter\Model;

use App\Domain\Pokemon\Model\SpeciesId;

/**
 * The once-a-day incense, and the rare encounters players use it for.
 *
 * The game files describe the item (fifteen minutes, delivered at midnight) but carry
 * no spawn table: that the Galarian birds are its prize is Niantic's announcement, not
 * something the data can confirm.
 */
final readonly class DailyAdventureIncense
{
    /**
     * @return non-empty-list<SpeciesId>
     */
    public static function rareTargets(): array
    {
        return [
            new SpeciesId('articuno_galarian'),
            new SpeciesId('zapdos_galarian'),
            new SpeciesId('moltres_galarian'),
        ];
    }
}
