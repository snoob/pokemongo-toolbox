<?php

declare(strict_types=1);

namespace App\Infrastructure\Json;

/**
 * Reads one key out of a decoded document and narrows it in the same breath, so
 * adapters never index an untyped array.
 */
final readonly class JsonPath
{
    /**
     * @return array<array-key, mixed>|null
     */
    public static function arrayAt(mixed $value, string $key): ?array
    {
        return JsonValue::asArray(self::at($value, $key));
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    public static function arrayListAt(mixed $value, string $key): array
    {
        return JsonValue::asArrayList(self::at($value, $key));
    }

    /**
     * @return list<float>
     */
    public static function floatListAt(mixed $value, string $key): array
    {
        return JsonValue::asFloatList(self::at($value, $key));
    }

    /**
     * @return array<string, float>|null
     */
    public static function floatMapAt(mixed $value, string $key): ?array
    {
        $array = self::arrayAt($value, $key);

        if (null === $array) {
            return null;
        }

        $map = [];

        // Narrowing through array_map keeps keys and leaves no `mixed` in a variable.
        foreach (array_map(JsonValue::asFloatOrNull(...), $array) as $entryKey => $number) {
            if (null === $number) {
                continue;
            }

            $map[(string) $entryKey] = $number;
        }

        return $map;
    }

    public static function stringAt(mixed $value, string $key): ?string
    {
        return JsonValue::asString(self::at($value, $key));
    }

    public static function intAt(mixed $value, string $key): ?int
    {
        return JsonValue::asInt(self::at($value, $key));
    }

    private static function at(mixed $value, string $key): mixed
    {
        $array = JsonValue::asArray($value);

        return null === $array ? null : $array[$key] ?? null;
    }
}
