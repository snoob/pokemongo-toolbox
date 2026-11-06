<?php

declare(strict_types=1);

namespace App\Infrastructure\Settings;

use App\Infrastructure\Json\JsonValue;

/**
 * One setting as the file holds it: a string, or a list of strings for a repeatable
 * option. A bare number is read as the string a command line would give.
 */
final readonly class SettingValue
{
    /**
     * @return null|string|list<string> null when it is neither
     */
    public static function fromJson(mixed $value): string|array|null
    {
        $string = \is_int($value) ? (string) $value : JsonValue::asString($value);

        if (null !== $string) {
            return $string;
        }

        $list = JsonValue::asArray($value);
        $strings = JsonValue::asStringList($value);

        return null === $list || !array_is_list($list) || \count($strings) !== \count($list) ? null : $strings;
    }
}
