<?php

declare(strict_types=1);

namespace App\UI\Cli\Input;

use App\Domain\Pvp\Model\Cp;

/**
 * Reads a `--cp` option, shared by every command that takes one.
 */
final readonly class CpOption
{
    public static function parse(?string $value): ?Cp
    {
        if (null === $value) {
            return null;
        }

        if (1 !== preg_match('/^\d{1,5}$/', trim($value)) || (int) $value < Cp::FLOOR) {
            throw new \InvalidArgumentException(\sprintf(
                'Cannot read a CP from "%s"; expected a number of at least %d.',
                $value,
                Cp::FLOOR,
            ));
        }

        return new Cp((int) $value);
    }
}
