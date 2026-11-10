<?php

declare(strict_types=1);

namespace App\UI\Cli\Input;

use App\Domain\Pokemon\Exception\InvalidIv;
use App\Domain\Pokemon\Model\IvPattern;
use App\Domain\Pokemon\Model\IvSpread;

/**
 * Reads the IVs of `inventory:add`: three IVs, or a number alone, which is a rank — IVs
 * always have a separator. No "#": the shell takes it for a comment and drops the rest.
 */
final readonly class SpreadOrRankArgument
{
    /**
     * All three IVs: the inventory ranks a spread, so none is left to deduce.
     *
     * @throws InvalidIv
     *
     * @return IvSpread|positive-int
     */
    public static function parse(string $value): IvSpread|int
    {
        if (1 === preg_match('/^\d+$/', trim($value))) {
            $rank = (int) $value;

            if ($rank < 1 || $rank > IvSpread::COMBINATIONS) {
                throw new \InvalidArgumentException(\sprintf(
                    'Rank %s is out of range; a rank runs from 1 to %d.',
                    trim($value),
                    IvSpread::COMBINATIONS,
                ));
            }

            return $rank;
        }

        return (
            IvPattern::fromString($value)->toSpread() ?? throw new \InvalidArgumentException(\sprintf(
                'IVs %s leave some unknown; the inventory needs all three, e.g. 1/15/14, or their rank.',
                $value,
            ))
        );
    }
}
