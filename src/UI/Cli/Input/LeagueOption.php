<?php

declare(strict_types=1);

namespace App\UI\Cli\Input;

use App\Domain\Pvp\Model\League;
use App\Infrastructure\Json\JsonValue;

/**
 * Reads a repeatable `--league` option, shared by every command that takes one, and a
 * league given as an argument.
 */
final readonly class LeagueOption
{
    /**
     * @return list<League> empty when the option was not given
     */
    public static function parse(mixed $option): array
    {
        if (!\is_array($option) || [] === $option) {
            return [];
        }

        return array_map(self::one(...), JsonValue::asStringList($option));
    }

    /**
     * One league by its name, as an argument takes it.
     */
    public static function one(string $value): League
    {
        return (
            League::tryFrom(strtolower(trim($value))) ?? throw new \InvalidArgumentException(\sprintf(
                'Unknown league "%s"; expected %s.',
                $value,
                implode(', ', self::names()),
            ))
        );
    }

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return array_map(static fn(League $l): string => $l->value, League::cases());
    }
}
