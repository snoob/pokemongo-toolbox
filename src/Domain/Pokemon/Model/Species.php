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
     * @param list<string> $forms      form slugs, e.g. ["alolan", "shadow"]; empty for the default form
     * @param list<MoveId>      $eliteMoves moves this form only learns from an Elite TM
     * @param list<PokemonType> $types      one or two; a regional form may differ from its base form
     * @param list<SpeciesId>   $evolutions the forms this one evolves into, one step ahead; megas excluded
     */
    public function __construct(
        public SpeciesId $id,
        public DexNumber $dex,
        public BaseStats $baseStats,
        public bool $shadowEligible,
        public bool $superMega = false,
        public array $forms = [],
        public array $eliteMoves = [],
        public array $types = [],
        public array $evolutions = [],
    ) {}

    public function hasType(PokemonType $type): bool
    {
        return \in_array($type, $this->types, true);
    }

    public function isBaseForm(): bool
    {
        return [] === $this->forms;
    }

    /**
     * Mega forms are spelled "mega", "mega_x" or "mega_y" among the form segments.
     */
    public function isMega(): bool
    {
        foreach ($this->forms as $form) {
            if (str_starts_with($form, 'mega')) {
                return true;
            }
        }

        return false;
    }

    public function isShadow(): bool
    {
        return \in_array('shadow', $this->forms, true);
    }

    /**
     * The form segments a user can type. A mega is reached as an evolution of the form
     * in hand, a shadow through its flag, so naming them would be a second way in.
     *
     * @return list<string>
     */
    public function searchableForms(): array
    {
        return array_values(array_filter(
            $this->forms,
            static fn(string $form): bool => 'shadow' !== $form && !str_starts_with($form, 'mega'),
        ));
    }

    /**
     * How many flag-covered segments this form carries. Zero means it is the one a bare
     * name should resolve to.
     */
    public function flagFormCount(): int
    {
        return \count($this->forms) - \count($this->searchableForms());
    }

    /**
     * Whether the move costs an Elite TM on this form — the thing worth knowing before
     * chasing a recommended moveset.
     */
    public function isElite(MoveId $move): bool
    {
        foreach ($this->eliteMoves as $elite) {
            if ($elite->equals($move)) {
                return true;
            }
        }

        return false;
    }
}
