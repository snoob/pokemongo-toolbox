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
     * @return array{id: string, dex: int, atk: int, def: int, sta: int, shadow: bool, super: bool, base: bool, forms: list<string>, elite: list<string>}|null null when the entry is unusable
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
        $tags = JsonValue::asStringList($entry['tags'] ?? null);

        return [
            'id' => $id,
            'dex' => $dex,
            'forms' => array_keys($forms),
            'atk' => $stats['atk'],
            'def' => $stats['def'],
            'sta' => $stats['sta'],
            'shadow' => \in_array('shadoweligible', $tags, true),
            // "supermega" marks the megas that carry an extra charged move.
            'super' => \in_array('supermega', $tags, true),
            // Elite moves are the ones an Elite TM unlocks: worth knowing before
            // spending one on a recommended moveset.
            'elite' => JsonValue::asStringList($entry['eliteMoves'] ?? null),
            'base' => [] === $forms,
        ];
    }
}
