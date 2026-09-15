<?php

declare(strict_types=1);

namespace App\UI\Cli\Presenter;

use App\Application\Pvp\RankPokemon\RankPokemonResult;
use App\Domain\Pokemon\Model\Move;
use App\Domain\Pvp\Model\MegaLevel;
use App\Domain\Pvp\Model\MegaLevelEffects;
use App\Infrastructure\Naming\MoveNameResolver;

/**
 * The lines under the table: what the numbers assume, and what the chosen Mega Level
 * actually grants. Kept out of the presenter, which already assembles three columns.
 */
final readonly class RankFooterNotes
{
    public function __construct(
        private BaseFormCellFormatter $baseForms,
        private MovesetFormatter $movesets,
        private MoveNameResolver $moveNames,
    ) {}

    /**
     * @return list<string>
     */
    public function of(RankPokemonResult $result, ?string $locale = null): array
    {
        $notes = [\sprintf('Level cap considered: %s.', $result->levelCap)];

        if ($this->baseForms->appliesTo($result)) {
            $notes[] = 'Pre-Mega CP = what to power the base form up to, and what Mega Evolving adds at that level.';
        }

        if (null !== $result->megaLevel && null !== $result->megaLevelEffects) {
            $notes[] = $this->megaLevel($result->megaLevel, $result->megaLevelEffects);
        }

        if ($result->species->superMega) {
            $notes[] = $this->superMega($result->megaLevelEffects, $result->exclusiveMove, $locale);
        }

        if ($this->showsAnEliteMove($result)) {
            $notes[] = \sprintf('%s = exclusive move, unlocked with an Elite TM.', MovesetFormatter::ELITE_MARK);
        }

        return $notes;
    }

    private function showsAnEliteMove(RankPokemonResult $result): bool
    {
        foreach ($result->outcomes as $outcome) {
            $moveset = $outcome->speciesRank?->moveset;

            if (null !== $moveset && $this->movesets->holdsAnEliteMove($moveset, $result->species)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The progression only touches the CP at its last step; the rest is about how often
     * the mega can be used, which is worth stating rather than implying.
     */
    private function megaLevel(MegaLevel $level, MegaLevelEffects $effects): string
    {
        return \sprintf(
            'Mega level %d: %d-day cooldown%s.',
            $level->value,
            $effects->cooldownDays,
            $this->combatPower($level, $effects),
        );
    }

    /**
     * Mega Evolving already lifts the CP a long way — that is what the "Pre-Mega CP"
     * column shows. What the *level* adds on top is a separate thing, and only the last
     * one adds any, so the wording must not let the two be read as one.
     */
    private function combatPower(MegaLevel $level, MegaLevelEffects $effects): string
    {
        if ($effects->additionalLevels > 0) {
            return \sprintf(', additional CP boost corresponding to +%d levels', $effects->additionalLevels);
        }

        // Nothing precedes the first level, so there is nothing to say it matches.
        return MegaLevel::MIN === $level->value
            ? ', CP boost'
            : \sprintf(', same CP boost as mega level %d', $level->value - 1);
    }

    /**
     * The exclusive move's base power is lifted by the Mega Level — the only per-level
     * effect that shows up in a battle.
     */
    private function superMega(?MegaLevelEffects $effects, ?Move $move, ?string $locale): string
    {
        $note = 'Super Mega Pokémon has an extra Charged Attack available when mega evolved';

        if (null === $move) {
            return $note . '.';
        }

        // The Mega Level lifts the move's base power; showing the bonus separately makes
        // the level's worth readable without doing the multiplication in your head.
        $power = null !== $effects && $effects->boostsTheExclusiveMove()
            ? \sprintf('%d + %d', $move->power, (int) round($move->power * ($effects->movePowerMultiplier - 1.0)))
            : (string) $move->power;

        return \sprintf('%s: %s (power: %s)', $note, $this->moveNames->name($move->id, $locale), $power);
    }
}
