<?php

declare(strict_types=1);

namespace App\UI\Cli\Presenter;

use App\Application\Inventory\Standing;
use App\Domain\Pvp\Model\RankedSpread;
use App\Domain\Pvp\Model\SpeciesRank;
use App\Infrastructure\Naming\SpeciesNameResolver;

/**
 * What a kept Pokémon is worth in its league, in the words the rank command uses.
 */
final readonly class StandingFormatter
{
    public function __construct(
        private SpeciesNameResolver $names,
    ) {}

    public function form(Standing $standing, ?string $locale): string
    {
        return $this->names->name($standing->form, $locale);
    }

    public function speciesRank(?SpeciesRank $rank): string
    {
        return null === $rank ? '<fg=gray>unranked</>' : \sprintf('#%d / %d', $rank->position, $rank->total);
    }

    public function spread(?RankedSpread $spread): string
    {
        if (null === $spread) {
            return '<fg=gray>ineligible (CP too high)</>';
        }

        $rank = \sprintf('#%d / %d — %.2f %%', $spread->position, $spread->total, $spread->percentOfBest);

        return $spread->isPerfect() ? \sprintf('<fg=green>%s</>', $rank) : $rank;
    }
}
