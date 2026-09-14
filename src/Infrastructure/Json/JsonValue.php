<?php

declare(strict_types=1);

namespace App\Infrastructure\Json;

/**
 * Narrows a single decoded value to a usable type, returning null rather than throwing:
 * upstream files carry entries we do not use and cannot vouch for. Keeping the narrowing
 * here means no adapter ever holds a `mixed` variable.
 */
final readonly class JsonValue
{
    /**
     * @return array<array-key, mixed>|null
     */
    public static function asArray(mixed $value): ?array
    {
        return \is_array($value) ? $value : null;
    }

    public static function asString(mixed $value): ?string
    {
        return \is_string($value) ? $value : null;
    }

    public static function asInt(mixed $value): ?int
    {
        return \is_int($value) ? $value : null;
    }

    public static function asFloat(mixed $value, float $default = 0.0): float
    {
        return self::asFloatOrNull($value) ?? $default;
    }

    public static function asFloatOrNull(mixed $value): ?float
    {
        return \is_numeric($value) ? (float) $value : null;
    }

    /**
     * Only the entries that are themselves arrays, so callers can iterate without
     * re-checking each element.
     *
     * @return list<array<array-key, mixed>>
     */
    public static function asArrayList(mixed $value): array
    {
        $entries = [];

        // Mapping first keeps the loop variable typed: no `mixed` ever lands in a variable.
        foreach (self::mapped($value, self::asArray(...)) as $entry) {
            if (null === $entry) {
                continue;
            }

            $entries[] = $entry;
        }

        return $entries;
    }

    /**
     * @return list<string>
     */
    public static function asStringList(mixed $value): array
    {
        $strings = [];

        foreach (self::mapped($value, self::asString(...)) as $entry) {
            if (null === $entry) {
                continue;
            }

            $strings[] = $entry;
        }

        return $strings;
    }

    /**
     * @return list<float>
     */
    public static function asFloatList(mixed $value): array
    {
        $numbers = [];

        foreach (self::mapped($value, self::asFloatOrNull(...)) as $entry) {
            if (null === $entry) {
                continue;
            }

            $numbers[] = $entry;
        }

        return $numbers;
    }

    /**
     * @template T
     *
     * @param callable(mixed): T $narrow
     *
     * @return list<T>
     */
    private static function mapped(mixed $value, callable $narrow): array
    {
        return \is_array($value) ? array_values(array_map($narrow, $value)) : [];
    }
}
