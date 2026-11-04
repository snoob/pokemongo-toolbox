<?php

declare(strict_types=1);

namespace App\UI\Cli\Input;

use App\Domain\Pvp\Model\League;
use App\Infrastructure\Json\JsonValue;

/**
 * Reads a repeatable `--league` option, shared by every command that takes one.
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

        $leagues = [];

        foreach (JsonValue::asStringList($option) as $value) {
            $league = League::tryFrom(strtolower(trim($value)));

            if (null === $league) {
                throw new \InvalidArgumentException(\sprintf(
                    'Unknown league "%s"; expected %s.',
                    $value,
                    implode(', ', array_map(static fn(League $l): string => $l->value, League::cases())),
                ));
            }

            $leagues[] = $league;
        }

        return $leagues;
    }
}
