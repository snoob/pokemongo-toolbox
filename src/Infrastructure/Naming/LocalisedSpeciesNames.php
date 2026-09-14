<?php

declare(strict_types=1);

namespace App\Infrastructure\Naming;

use App\Domain\Pokemon\Model\Species;

/**
 * Every name a form answers to, across the locales we ship. Typing an English name must
 * work even when the interface speaks French, so the search index holds them all.
 */
final readonly class LocalisedSpeciesNames
{
    /**
     * @param list<string> $locales
     */
    public function __construct(
        private SpeciesNameResolver $resolver,
        private array $locales,
    ) {}

    /**
     * @return list<string>
     */
    public function of(Species $species): array
    {
        $names = [];

        foreach ($this->locales as $locale) {
            $names[] = $this->resolver->name($species, $locale);
        }

        return array_values(array_unique($names));
    }
}
