<?php

declare(strict_types=1);

namespace App\UI\Cli\Presenter;

use App\Application\Pvp\RankPokemon\LeagueOutcome;
use App\Application\Pvp\RankPokemon\RankPokemonResult;
use App\Domain\Pvp\Model\RankedSpread;

/**
 * Everything the IV column says for one league: where the given spread lands, which
 * spread to hunt for, and — for a mega — what the base form must read before evolving.
 */
final readonly class IvCellFormatter
{
    public function format(LeagueOutcome $outcome, RankPokemonResult $result): string
    {
        if ($outcome->ineligible) {
            return '<fg=gray>ineligible (CP too high)</>';
        }

        $lines = [];

        if (null !== $result->iv && null !== $outcome->spread) {
            $iv = (string) $result->iv;

            $lines[] = \sprintf(
                '%s  <options=bold>#%d / %d</> — %.2f %% — %s',
                $outcome->spread->isPerfect() ? $this->asUnbeatable($iv) : $iv,
                $outcome->spread->position,
                $outcome->spread->total,
                $outcome->spread->percentOfBest,
                $this->cpAndLevel($outcome->spread, $outcome),
            );
        }

        // Nothing to add when the given IVs already are the best: the line would just
        // repeat what sits above it.
        if (null !== $outcome->best && true !== $outcome->spread?->isPerfect()) {
            $lines[] = \sprintf(
                '%s  best — %s',
                $this->asUnbeatable((string) $outcome->best->iv),
                $this->cpAndLevel($outcome->best, $outcome),
            );
        }

        return [] === $lines ? '<fg=gray>—</>' : implode("\n", $lines);
    }

    /**
     * Green marks a spread that cannot be beaten in this league.
     */
    private function asUnbeatable(string $iv): string
    {
        return \sprintf('<fg=green>%s</>', $iv);
    }

    /**
     * Without a CP cap every spread reaches the level cap, so naming the level would
     * repeat the same number on every row.
     */
    private function cpAndLevel(RankedSpread $spread, LeagueOutcome $outcome): string
    {
        return null === $outcome->league->cpCap()
            ? \sprintf('CP %d', $spread->cp->value)
            : \sprintf('CP %d at level %s', $spread->cp->value, $spread->level);
    }
}
