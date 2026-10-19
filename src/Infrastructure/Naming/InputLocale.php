<?php

declare(strict_types=1);

namespace App\Infrastructure\Naming;

use App\Domain\Pokemon\Model\Species;
use App\Infrastructure\Catalog\NameNormalizer;
use App\Infrastructure\Catalog\NearestCandidates;
use App\Infrastructure\Catalog\TypoTolerance;

/**
 * Picks the display locale from the way the Pokémon was asked for: type "Ectoplasma"
 * and the whole answer speaks French, type "Gengar" or "94" and it speaks English.
 *
 * Many names are spelled the same in both languages (Pikachu, Alakazam). Those match
 * everywhere, so they settle on the default rather than picking a language at random.
 *
 * A misspelt name speaks the language of the name it is nearest to: "flozizarre" is
 * French. The exact match is only the nearest at distance zero.
 */
final readonly class InputLocale
{
    /**
     * @param list<string> $locales
     */
    public function __construct(
        private SpeciesNameResolver $resolver,
        private NameNormalizer $normalizer,
        private TypoTolerance $tolerance,
        private array $locales,
        private string $default,
    ) {}

    public function detect(string $identifier, Species $species): string
    {
        $needle = $this->normalizer->normalize($identifier);

        if ('' === $needle) {
            return $this->default;
        }

        /** @var NearestCandidates<string> $nearest */
        $nearest = new NearestCandidates();

        foreach ($this->locales as $locale) {
            $name = $this->normalizer->normalize($this->resolver->searchName($species, $locale));
            $nearest->offer($this->tolerance->distance($needle, $name), [$locale]);
        }

        $matches = $nearest->items();

        // One match means the language is unambiguous; none (a dex number) or several
        // (a name shared by both languages) means we learned nothing.
        return 1 === \count($matches) ? $matches[0] : $this->default;
    }
}
