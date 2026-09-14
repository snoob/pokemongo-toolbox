<?php

declare(strict_types=1);

namespace App\Infrastructure\Build;

use App\Infrastructure\Json\JsonValue;

/**
 * Build-time only: pulls the three Pokémon GO base stats out of a raw entry.
 *
 * A base stat of zero means the entry is incomplete upstream, not that the Pokémon is
 * weak — reporting null here beats writing a species file that throws on every later
 * read, since BaseStats rightly refuses a zero.
 */
final readonly class BaseStatsReader
{
    /**
     * @return array{atk: int, def: int, sta: int}|null
     */
    public function read(mixed $value): ?array
    {
        $stats = JsonValue::asArray($value);

        if (null === $stats) {
            return null;
        }

        $attack = $this->stat($stats, 'atk');
        $defense = $this->stat($stats, 'def');
        $stamina = $this->stat($stats, 'hp');

        if (null === $attack || null === $defense || null === $stamina) {
            return null;
        }

        return ['atk' => $attack, 'def' => $defense, 'sta' => $stamina];
    }

    /**
     * @param array<array-key, mixed> $stats
     */
    private function stat(array $stats, string $key): ?int
    {
        $value = (int) JsonValue::asFloat($stats[$key] ?? null);

        return $value > 0 ? $value : null;
    }
}
