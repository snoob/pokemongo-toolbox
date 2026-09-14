<?php

declare(strict_types=1);

namespace App\Infrastructure\Build;

use App\Infrastructure\Json\JsonValue;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Build-time only: reads the form segments out of a game name.
 *
 * "Gengar" has none, "Gengar (Shadow)" has one, "Raichu (Alolan) (Shadow)" has two.
 * The slug is what the species file stores; the label feeds the English catalogue.
 */
final readonly class FormLabels
{
    public function __construct(
        private AsciiSlugger $slugger = new AsciiSlugger('en'),
    ) {}

    /**
     * @return array<string, string> slug => English label, in the order they appear
     */
    public function of(string $gameName): array
    {
        $matches = [];
        preg_match_all('#\(([^()]+)\)#', $gameName, $matches);

        $labels = [];

        // A name without parentheses simply yields an empty capture list.
        foreach (JsonValue::asStringList($matches[1] ?? null) as $label) {
            $trimmed = trim($label);

            if ('' !== $trimmed) {
                $labels[$this->slug($trimmed)] = $trimmed;
            }
        }

        return $labels;
    }

    private function slug(string $label): string
    {
        return $this->slugger->slug($label, '_', 'en')->lower()->toString();
    }
}
