<?php

declare(strict_types=1);

namespace App\Domain\Pokemon\Model;

/**
 * Aggregate root: one playable form, with the stats the PvP maths need.
 *
 * It carries no human-readable name on purpose. A name is a presentation concern and
 * depends on a locale; the identity of a form is its id, its Pokédex number and the
 * form segments it is made of.
 */
final readonly class Species
{
    /**
     * @param list<string> $forms form slugs, e.g. ["alolan", "shadow"]; empty for the default form
     */
    public function __construct(
        public SpeciesId $id,
        public DexNumber $dex,
        public BaseStats $baseStats,
        public bool $shadowEligible,
        public array $forms = [],
    ) {}

    public function isBaseForm(): bool
    {
        return [] === $this->forms;
    }
}
