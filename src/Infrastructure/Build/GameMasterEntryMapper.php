<?php

declare(strict_types=1);

namespace App\Infrastructure\Build;

use App\Infrastructure\Json\JsonValue;

/**
 * Build-time only: validates one raw game-data entry and flattens it into the shape
 * the species file stores. Names are deliberately absent: they live in the translation
 * catalogues, keyed by Pokédex number and form slug.
 */
final readonly class GameMasterEntryMapper
{
    public function __construct(
        private BaseStatsReader $baseStats,
        private FormLabels $formLabels,
    ) {}

    /**
     * @param array<array-key, mixed> $entry
     *
     * @return array{id: string, dex: int, atk: int, def: int, sta: int, shadow: bool, base: bool, forms: list<string>}|null null when the entry is unusable
     */
    public function map(array $entry): ?array
    {
        $id = JsonValue::asString($entry['speciesId'] ?? null);
        $name = JsonValue::asString($entry['speciesName'] ?? null);
        $dex = JsonValue::asInt($entry['dex'] ?? null);

        if (null === $id || null === $name || null === $dex || $dex < 1) {
            return null;
        }

        $stats = $this->baseStats->read($entry['baseStats'] ?? null);

        if (null === $stats) {
            return null;
        }

        // A parenthesised segment marks an alternate form: "Gengar (Shadow)",
        // "Raichu (Alolan) (Shadow)". A bare name is the form people mean by default.
        $forms = $this->formLabels->of($name);

        return [
            'id' => $id,
            'dex' => $dex,
            'forms' => array_keys($forms),
            'atk' => $stats['atk'],
            'def' => $stats['def'],
            'sta' => $stats['sta'],
            'shadow' => \in_array('shadoweligible', JsonValue::asStringList($entry['tags'] ?? null), true),
            'base' => [] === $forms,
        ];
    }
}
