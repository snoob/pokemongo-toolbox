<?php

declare(strict_types=1);

namespace App\UI\Cli\Presenter;

use App\Application\Pvp\RankPokemon\EvolutionRanking;
use App\Application\Pvp\RankPokemon\RankPokemonResult;
use App\Domain\Pvp\Model\MegaLevel;
use App\Domain\Pvp\Model\MegaLevelEffects;
use App\Infrastructure\Naming\MoveNameResolver;
use App\Infrastructure\Naming\SpeciesNameResolver;

/**
 * The notes only a mega brings: what the chosen Mega Level grants, and the extra move of
 * each Super Mega in the family.
 */
final readonly class MegaFooterNotes
{
    public function __construct(
        private MoveNameResolver $moveNames,
        private SpeciesNameResolver $names,
    ) {}

    /**
     * @return list<string>
     */
    public function of(RankPokemonResult $result, ?string $locale = null): array
    {
        $notes = [];

        if (null !== $result->megaLevel && null !== $result->megaLevelEffects) {
            $notes[] = $this->megaLevel($result->megaLevel, $result->megaLevelEffects);
        }

        foreach ($result->evolutions as $ranking) {
            if (!$ranking->species()->superMega) {
                continue;
            }

            $notes[] = $this->superMega($ranking, $result->megaLevelEffects, $locale);
        }

        return $notes;
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
     * Mega Evolving already lifts the CP a long way — that is what the power-up column
     * shows. What the *level* adds on top is a separate thing, and only the last
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
     * effect that shows up in a battle. A family can hold two Super Megas (Gardevoir and
     * Gallade), so each note names its own.
     */
    private function superMega(EvolutionRanking $ranking, ?MegaLevelEffects $effects, ?string $locale): string
    {
        $note = \sprintf('%s is a Super Mega, with an extra Charged Attack', $this->names->name(
            $ranking->species(),
            $locale,
        ));
        $move = $ranking->exclusiveMove;

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
