<?php

declare(strict_types=1);

namespace App\UI\Cli\Presenter;

use App\Domain\Pvp\Model\IvDeduction;
use App\Domain\Pvp\Model\PokemonLevel;

/**
 * How a CP settled the IVs, in one line: before the tables, and in the list of spreads
 * when it settled nothing.
 */
final readonly class IvDeductionFormatter
{
    /**
     * A CP shown before the tables, so that a wrong reading shows before it is used.
     */
    public function summary(IvDeduction $deduction): string
    {
        return $deduction->guessed
            ? \sprintf('Deduced %s from CP %d (%s).', $deduction->iv, $deduction->cp->value, $this->levels($deduction))
            : \sprintf(
                'CP %d checks out for %s (%s).',
                $deduction->cp->value,
                $deduction->iv,
                $this->levels($deduction),
            );
    }

    public function candidate(IvDeduction $deduction): string
    {
        return \sprintf('%s (%s)', $deduction->iv, $this->levels($deduction));
    }

    private function levels(IvDeduction $deduction): string
    {
        return \sprintf(
            '%s %s',
            1 === \count($deduction->levels) ? 'level' : 'levels',
            implode(' or ', array_map(static fn(PokemonLevel $level): string => (string) $level, $deduction->levels)),
        );
    }
}
