<?php

declare(strict_types=1);

namespace App\Domain\Pokemon\Exception;

final class InvalidIv extends \InvalidArgumentException
{
    public static function outOfRange(string $stat, int $value): self
    {
        return new self(\sprintf('IV "%s" must be between 0 and 15, got %d.', $stat, $value));
    }

    public static function unparsable(string $input): self
    {
        return new self(\sprintf(
            'Cannot read IVs from "%s"; expected the form "attack/defense/stamina", e.g. "1/15/14".',
            $input,
        ));
    }
}
