<?php

declare(strict_types=1);

namespace App\UI\Cli\Presenter;

use App\Application\Pvp\RankPokemon\EvolutionRanking;
use App\Application\Pvp\RankPokemon\RankPokemonResult;
use App\Infrastructure\Naming\SpeciesNameResolver;

/**
 * The lines under the tables: what the numbers assume and what the marks mean, said once
 * for the whole family rather than under every table. The mega ones come from
 * `MegaFooterNotes`.
 */
final readonly class RankFooterNotes
{
    public function __construct(
        private BaseFormCellFormatter $baseForms,
        private MovesetFormatter $movesets,
        private MegaFooterNotes $megaNotes,
        private SpeciesNameResolver $names,
    ) {}

    /**
     * @return list<string>
     */
    public function of(RankPokemonResult $result, ?string $locale = null): array
    {
        $notes = [\sprintf('Level cap considered: %s.', $result->levelCap)];

        if ($result->floor->restricts()) {
            $notes[] = \sprintf('IV floor considered: %s.', $result->floor);
        }

        if ($this->any($result, $this->baseForms->appliesTo(...))) {
            $notes[] = $this->powerUp($result, $locale);
        }

        $notes = [...$notes, ...$this->megaNotes->of($result, $locale)];

        if ($this->any($result, $this->showsAnEliteMove(...))) {
            $notes[] = \sprintf('%s = exclusive move, unlocked with an Elite TM.', MovesetFormatter::ELITE_MARK);
        }

        return $notes;
    }

    /**
     * @param \Closure(EvolutionRanking): bool $test
     */
    private function any(RankPokemonResult $result, \Closure $test): bool
    {
        foreach ($result->evolutions as $ranking) {
            if ($test($ranking)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The column is named after the form in hand, so the note can say it plainly; the
     * percentage only shows one step from a mega, and is only explained then.
     */
    private function powerUp(RankPokemonResult $result, ?string $locale): string
    {
        $name = $this->names->name($result->asked, $locale);
        $note = \sprintf('%s CP = what to power %s up to before evolving', $name, $name);

        return $this->any($result, $this->baseForms->showsAMegaGain(...))
            ? $note . ', and what Mega Evolving adds at that level.'
            : $note . '.';
    }

    private function showsAnEliteMove(EvolutionRanking $ranking): bool
    {
        foreach ($ranking->outcomes as $outcome) {
            $moveset = $outcome->speciesRank?->moveset;

            if (null !== $moveset && $this->movesets->holdsAnEliteMove($moveset, $ranking->species())) {
                return true;
            }
        }

        return false;
    }
}
