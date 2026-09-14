<?php

declare(strict_types=1);

namespace App\Infrastructure\Catalog;

use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Folds a user-typed name to a comparable key: "Ectoplasma", "ectoplasma" and
 * "ÉCTOPLASMA" all collapse to the same thing, as do "Mr. Mime" and "mr mime".
 *
 * The folding is the String component's job (GUIDELINES.md §8); what belongs to us is
 * the matching policy: no separator at all, so punctuation and spacing never matter.
 */
final readonly class NameNormalizer
{
    private AsciiSlugger $slugger;

    public function __construct()
    {
        // Gender signs carry meaning — Nidoran♀ and Nidoran♂ are different species — so
        // they must become letters *before* transliteration drops them. Only the closure
        // form of the symbols map runs early enough for that.
        $this->slugger = new AsciiSlugger('fr', static fn(string $value, ?string $_locale): string => strtr($value, [
            '♀' => ' female ',
            '♂' => ' male ',
        ]));
    }

    public function normalize(string $value): string
    {
        return $this->slugger->slug($value, '', 'fr')->lower()->toString();
    }
}
