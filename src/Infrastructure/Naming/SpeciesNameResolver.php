<?php

declare(strict_types=1);

namespace App\Infrastructure\Naming;

use App\Domain\Pokemon\Model\Species;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Builds the display name of a form from the translation catalogues: the species name
 * keyed by Pokédex number, then one segment per form slug.
 *
 * "Ectoplasma", "Ectoplasma (Obscur)", "Raichu (d'Alola) (Obscur)".
 */
final readonly class SpeciesNameResolver
{
    public const string SPECIES_DOMAIN = 'pokemon';
    public const string FORM_DOMAIN = 'pokemon_form';

    public function __construct(
        private TranslatorInterface $translator,
    ) {}

    public function name(Species $species, ?string $locale = null): string
    {
        return $this->compose($species, $species->forms, $locale);
    }

    /**
     * The name the search index holds: mega and shadow are reached through flags, so
     * their segments are left out rather than offering a second way in.
     */
    public function searchName(Species $species, ?string $locale = null): string
    {
        return $this->compose($species, $species->searchableForms(), $locale);
    }

    /**
     * @param list<string> $forms
     */
    private function compose(Species $species, array $forms, ?string $locale): string
    {
        $name = $this->translator->trans((string) $species->dex->value, domain: self::SPECIES_DOMAIN, locale: $locale);

        foreach ($forms as $form) {
            $name .= \sprintf(' (%s)', $this->translator->trans($form, domain: self::FORM_DOMAIN, locale: $locale));
        }

        return $name;
    }
}
