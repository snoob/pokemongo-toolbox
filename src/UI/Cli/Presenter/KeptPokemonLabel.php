<?php

declare(strict_types=1);

namespace App\UI\Cli\Presenter;

use App\Application\Inventory\ListInventory\InventoryLine;
use App\Infrastructure\Naming\SpeciesNameResolver;

/**
 * One kept Pokémon on a line, without its number: what `inventory:remove` offers to pick
 * and says it removed.
 */
final readonly class KeptPokemonLabel
{
    public function __construct(
        private SpeciesNameResolver $names,
        private StandingFormatter $standings,
    ) {}

    /**
     * "Dratini 0/15/15, Great League — IVs #6 as Dragonair": what tells two kept Pokémon apart.
     */
    public function of(InventoryLine $line, ?string $locale): string
    {
        $entry = $line->entry;
        $name = null === $line->species ? $entry->species->value : $this->names->name($line->species, $locale);
        $text = \sprintf('%s %s, %s', $name, $entry->iv, $entry->league->label());

        if (null === $line->standing || null === $line->standing->spread) {
            return $text;
        }

        return \sprintf(
            '%s — IVs #%d as %s',
            $text,
            $line->standing->spread->position,
            $this->standings->form($line->standing, $locale),
        );
    }
}
