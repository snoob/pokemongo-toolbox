<?php

declare(strict_types=1);

namespace App\UI\Cli\Presenter;

use App\Application\Pvp\RankPokemon\LeagueOutcome;
use App\Application\Pvp\RankPokemon\RankPokemonResult;
use App\Domain\Pvp\Model\PowerUpTarget;

/**
 * The "Hors méga" column: what the base form must read on screen before Mega Evolving.
 *
 * It only exists for a mega in a capped league — an uncapped one asks nothing of the CP,
 * and a plain species never leaves its own form.
 */
final readonly class BaseFormCellFormatter
{
    public const string HEADER = 'Pre-Mega CP';

    public function appliesTo(RankPokemonResult $result): bool
    {
        foreach ($result->outcomes as $outcome) {
            if (null !== $outcome->powerUpTo || null !== $outcome->bestPowerUpTo) {
                return true;
            }
        }

        return false;
    }

    /**
     * Mirrors the IV column line for line: the given spread first, the unbeatable one
     * below it in green.
     */
    /**
     * The Pokémon level to stop at — the figure that makes the CP target checkable while
     * powering up. Not to be confused with the Mega Level, which is a separate
     * progression and only affects the CP from level 4 onwards.
     */
    private function target(PowerUpTarget $target): string
    {
        return \sprintf('%d CP (lvl %s) +%d %%', $target->cp->value, $target->level, $target->megaGainPercent);
    }

    public function format(LeagueOutcome $outcome): string
    {
        $lines = [];

        if (null !== $outcome->powerUpTo) {
            $lines[] = $this->target($outcome->powerUpTo);
        }

        if (null !== $outcome->bestPowerUpTo && true !== $outcome->spread?->isPerfect()) {
            $lines[] = \sprintf('<fg=green>%s</>', $this->target($outcome->bestPowerUpTo));
        }

        return [] === $lines ? '<fg=gray>—</>' : implode("\n", $lines);
    }
}
