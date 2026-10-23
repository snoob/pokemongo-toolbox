<?php

declare(strict_types=1);

namespace App\Domain\Pokemon\Exception;

use App\Domain\Pokemon\Model\IvFloor;
use App\Domain\Pokemon\Model\IvSpread;

final class InvalidIv extends \InvalidArgumentException
{
    public static function outOfRange(string $stat, int $value): self
    {
        return new self(\sprintf('IV "%s" must be between 0 and 15, got %d.', $stat, $value));
    }

    /**
     * @param list<string> $names
     */
    public static function unknownFloor(string $input, array $names): self
    {
        return new self(\sprintf(
            'Unknown IV floor "%s"; expected %s, or a number from 0 to 15.',
            $input,
            implode(', ', $names),
        ));
    }

    public static function belowFloor(IvSpread $iv, IvFloor $floor): self
    {
        return new self(\sprintf('IVs %s cannot come with a floor of %s.', $iv, $floor));
    }

    public static function unparsable(string $input): self
    {
        return new self(\sprintf(
            'Cannot read IVs from "%s"; expected the form "attack/defense/stamina", e.g. "1/15/14".',
            $input,
        ));
    }
}
