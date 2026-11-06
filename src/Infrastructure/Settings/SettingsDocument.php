<?php

declare(strict_types=1);

namespace App\Infrastructure\Settings;

use App\Infrastructure\Json\JsonValue;

/**
 * The settings file's format, apart from reading and writing it: an object per command
 * family, holding the default of each option as a string, or a list of strings for a
 * repeatable one. An empty string or list counts as unset.
 *
 * @phpstan-type Sections array<string, array<string, string|list<string>>>
 */
final readonly class SettingsDocument
{
    /**
     * @param Sections $sections
     */
    public static function encode(array $sections): string
    {
        return (
            json_encode(
                [] === $sections ? new \stdClass() : $sections,
                \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR,
            ) . "\n"
        );
    }

    /**
     * @throws \UnexpectedValueException naming what is wrong
     *
     * @return Sections
     */
    public static function decode(string $json): array
    {
        try {
            $document = self::objectOrNull(json_decode($json, true, flags: \JSON_THROW_ON_ERROR));
        } catch (\JsonException $e) {
            throw new \UnexpectedValueException($e->getMessage(), previous: $e);
        }

        if (null === $document) {
            throw new \UnexpectedValueException('the file must hold an object');
        }

        $sections = [];

        foreach (array_map(self::objectOrNull(...), $document) as $section => $options) {
            if (null === $options) {
                throw new \UnexpectedValueException(\sprintf('"%s" must be an object', $section));
            }

            foreach (array_map(SettingValue::fromJson(...), $options) as $option => $value) {
                if (null === $value) {
                    throw new \UnexpectedValueException(\sprintf(
                        '"%s.%s" must be a string or a list of strings',
                        $section,
                        $option,
                    ));
                }

                if ('' !== $value && [] !== $value) {
                    $sections[(string) $section][(string) $option] = $value;
                }
            }
        }

        return $sections;
    }

    /**
     * @return ?array<array-key, mixed>
     */
    private static function objectOrNull(mixed $value): ?array
    {
        $object = JsonValue::asArray($value);

        return null === $object || [] !== $object && array_is_list($object) ? null : $object;
    }
}
