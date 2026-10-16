<?php

declare(strict_types=1);

namespace App\UI\Cli\Presenter;

use App\Application\Pvp\RankPokemon\EvolutionRanking;
use App\Application\Pvp\RankPokemon\LeagueOutcome;
use App\Domain\Pokemon\Model\Species;
use App\Domain\Pvp\Model\PowerUpTarget;
use App\Infrastructure\Naming\SpeciesNameResolver;

/**
 * The "<form in hand> CP" column: what the asked form must read on screen before
 * evolving — into another form or into its mega, the same question either way.
 *
 * It only exists for an evolution in a capped league — an uncapped one asks nothing of
 * the CP, and the form in hand ranked as itself never leaves its own form.
 */
final readonly class BaseFormCellFormatter
{
    public function __construct(
        private SpeciesNameResolver $names,
    ) {}

    public function header(Species $asked, ?string $locale): string
    {
        return \sprintf('%s CP', $this->names->name($asked, $locale));
    }

    public function appliesTo(EvolutionRanking $ranking): bool
    {
        foreach ($ranking->outcomes as $outcome) {
            if (null !== $outcome->powerUpTo || null !== $outcome->bestPowerUpTo) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a cell tells what Mega Evolving adds — only one step away from the mega.
     */
    public function showsAMegaGain(EvolutionRanking $ranking): bool
    {
        foreach ($ranking->outcomes as $outcome) {
            if (null !== $outcome->powerUpTo?->megaGainPercent || null !== $outcome->bestPowerUpTo?->megaGainPercent) {
                return true;
            }
        }

        return false;
    }

    /**
     * Mirrors the IV column line for line: the given spread first, the unbeatable one
     * below it in green.
     */
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

    /**
     * The Pokémon level to stop at — the figure that makes the CP target checkable while
     * powering up. Not to be confused with the Mega Level, which is a separate
     * progression and only affects the CP from level 4 onwards.
     */
    private function target(PowerUpTarget $target): string
    {
        $cell = \sprintf('%d CP (lvl %s)', $target->cp->value, $target->level);

        return null === $target->megaGainPercent ? $cell : \sprintf('%s +%d %%', $cell, $target->megaGainPercent);
    }
}
