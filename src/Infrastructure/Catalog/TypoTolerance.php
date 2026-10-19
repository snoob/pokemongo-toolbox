<?php

declare(strict_types=1);

namespace App\Infrastructure\Catalog;

/**
 * How far a typed name may stray from a real one and still be taken for it: the
 * Levenshtein distance between the two normalised keys, within an allowance that grows
 * with the length. One slip in "mewto", two in "dracofeu", none in "mew" — a three-letter
 * name with one letter changed is another word, not a typo.
 *
 * The distance is PHP's own `levenshtein()` (GUIDELINES.md §8); what belongs to us is the
 * allowance. Keys come out of `NameNormalizer`, plain ASCII, so counting bytes is
 * counting letters.
 */
final readonly class TypoTolerance
{
    /**
     * The number of edits between the two keys, or null when there are too many for a typo.
     */
    public function distance(string $typed, string $name): ?int
    {
        $distance = levenshtein($typed, $name);

        return $distance <= $this->allowanceFor($typed) ? $distance : null;
    }

    /**
     * The same, against the start of a longer name: "flozizar" is one edit from the
     * "florizar" that begins "florizarre". Null when the name is no longer than the input,
     * since distance() already covers it.
     */
    public function distanceToPrefix(string $typed, string $name): ?int
    {
        if (\strlen($name) <= \strlen($typed)) {
            return null;
        }

        return $this->distance($typed, substr($name, 0, \strlen($typed)));
    }

    private function allowanceFor(string $typed): int
    {
        return match (true) {
            \strlen($typed) <= 3 => 0,
            \strlen($typed) <= 5 => 1,
            \strlen($typed) <= 9 => 2,
            default => 3,
        };
    }
}
