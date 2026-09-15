<?php

declare(strict_types=1);

namespace App\Infrastructure\Naming;

use App\Domain\Pokemon\Model\Species;
use App\Infrastructure\Catalog\NameNormalizer;

/**
 * Picks the display locale from the way the Pokémon was asked for: type "Ectoplasma"
 * and the whole answer speaks French, type "Gengar" or "94" and it speaks English.
 *
 * Many names are spelled the same in both languages (Pikachu, Alakazam). Those match
 * everywhere, so they settle on the default rather than picking a language at random.
 */
final readonly class InputLocale
{
    /**
     * @param list<string> $locales
     */
    public function __construct(
        private SpeciesNameResolver $resolver,
        private NameNormalizer $normalizer,
        private array $locales,
        private string $default,
    ) {}

    public function detect(string $identifier, Species $species): string
    {
        $needle = $this->normalizer->normalize($identifier);

        if ('' === $needle) {
            return $this->default;
        }

        $matches = [];

        foreach ($this->locales as $locale) {
            if ($this->normalizer->normalize($this->resolver->searchName($species, $locale)) !== $needle) {
                continue;
            }

            $matches[] = $locale;
        }

        // One match means the language is unambiguous; none (a dex number) or several
        // (a name shared by both languages) means we learned nothing.
        return 1 === \count($matches) ? $matches[0] : $this->default;
    }
}
