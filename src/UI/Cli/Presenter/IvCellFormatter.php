<?php

declare(strict_types=1);

namespace App\UI\Cli\Presenter;

use App\Application\Pvp\RankPokemon\LeagueOutcome;
use App\Domain\Pokemon\Model\IvFloor;
use App\Domain\Pokemon\Model\IvSpread;
use App\Domain\Pvp\Model\RankedSpread;

/**
 * Everything the IV column says for one league: where the given spread lands, and which
 * spread to hunt for. Under an IV floor both keep their place among all 4096 — that is
 * what the floor costs — and the given spread also says where it stands among the
 * spreads the floor leaves possible, while the best of all stays in sight.
 */
final readonly class IvCellFormatter
{
    public function format(LeagueOutcome $outcome, ?IvSpread $iv, IvFloor $floor): string
    {
        // A floor can leave out every spread under the cap while a wild catch still fits.
        if ($outcome->ineligible) {
            return null === $outcome->wildBest
                ? '<fg=gray>ineligible (CP too high)</>'
                : \sprintf(
                    "<fg=gray>nothing above floor %d fits under the cap</>\n%s",
                    $floor->minimum,
                    $this->wildBestLine($outcome->wildBest, $outcome),
                );
        }

        $lines = [];
        $isBest = null !== $outcome->best && true === $outcome->spread?->iv->equals($outcome->best->iv);

        if (null !== $iv && null !== $outcome->spread) {
            $given = (string) $iv;

            $lines[] = \sprintf(
                '%s  <options=bold>#%d / %d</>%s — %.2f %% — %s',
                $isBest ? $this->asUnbeatable($given) : $given,
                $outcome->spread->position,
                $outcome->spread->total,
                $this->withinFloor($outcome, $floor),
                $outcome->spread->percentOfBest,
                $this->cpAndLevel($outcome->spread, $outcome),
            );
        }

        // Nothing to add when the given IVs already are the best: the line would just
        // repeat what sits above it.
        if (null !== $outcome->best && !$isBest) {
            $lines[] = \sprintf(
                '%s  %s — %s',
                $this->asUnbeatable((string) $outcome->best->iv),
                $floor->restricts()
                    ? \sprintf(
                        'best above floor %d, #%d — %.2f %%',
                        $floor->minimum,
                        $outcome->best->position,
                        $outcome->best->percentOfBest,
                    )
                    : 'best',
                $this->cpAndLevel($outcome->best, $outcome),
            );
        }

        // Under a floor, what a wild catch could give stays in sight — unless the floor
        // leaves it in, as 15/15/15 in Master League.
        if (null !== $outcome->wildBest && true !== $outcome->best?->iv->equals($outcome->wildBest->iv)) {
            $lines[] = $this->wildBestLine($outcome->wildBest, $outcome);
        }

        return [] === $lines ? '<fg=gray>—</>' : implode("\n", $lines);
    }

    /**
     * Not green: the Pokémon in hand cannot have come with it.
     */
    private function wildBestLine(RankedSpread $wildBest, LeagueOutcome $outcome): string
    {
        return \sprintf('%s  best overall — %s', $wildBest->iv, $this->cpAndLevel($wildBest, $outcome));
    }

    private function withinFloor(LeagueOutcome $outcome, IvFloor $floor): string
    {
        $rank = $outcome->withinFloor;

        return $floor->restricts() && null !== $rank
            ? \sprintf(' · #%d / %d above floor %d', $rank->position, $rank->total, $floor->minimum)
            : '';
    }

    /**
     * Green marks a spread that cannot be beaten in this league, from where the Pokémon
     * comes from.
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
