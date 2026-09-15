<?php

declare(strict_types=1);

namespace App\UI\Cli\Presenter;

use App\Application\Pvp\RankPokemon\LeagueOutcome;
use App\Application\Pvp\RankPokemon\RankPokemonResult;
use App\Domain\Pokemon\Model\Species;
use App\Infrastructure\Naming\SpeciesNameResolver;
use Symfony\Component\Console\Style\SymfonyStyle;

final readonly class PvpRankTextPresenter
{
    public function __construct(
        private SpeciesNameResolver $names,
        private MovesetFormatter $movesets,
        private IvCellFormatter $ivCells,
        private BaseFormCellFormatter $baseForms,
        private RankFooterNotes $notes,
    ) {}

    public function present(SymfonyStyle $io, RankPokemonResult $result, ?string $locale = null): void
    {
        $species = $result->species;

        $io->title(\sprintf('%s — #%d', $this->names->name($species, $locale), $species->dex->value));

        // The base-form column only earns its width when a mega is on screen.
        $showsBaseForm = $this->baseForms->appliesTo($result);
        $rows = [];

        foreach ($result->outcomes as $outcome) {
            $row = [
                $outcome->league->label(),
                $this->speciesRankCell($outcome, $species, $locale),
                $this->ivCells->format($outcome, $result),
            ];

            if ($showsBaseForm) {
                $row[] = $this->baseForms->format($outcome);
            }

            $rows[] = $row;
        }

        $headers = ['League', 'Species rank', 'IVs'];

        if ($showsBaseForm) {
            $headers[] = BaseFormCellFormatter::HEADER;
        }

        $io->table($headers, $rows);

        foreach ($this->notes->of($result, $locale) as $note) {
            $io->text(\sprintf('<comment>%s</comment>', $note));
        }
    }

    private function speciesRankCell(LeagueOutcome $outcome, Species $species, ?string $locale): string
    {
        $rank = $outcome->speciesRank;

        if (null === $rank) {
            return '<fg=gray>unranked</>';
        }

        $lines = [\sprintf('#%d / %d  (score %.1f)', $rank->position, $rank->total, $rank->score)];

        if (null !== $rank->moveset) {
            $lines[] = $this->movesets->format($rank->moveset, $species, $locale);
        }

        return implode("\n", $lines);
    }

    /**
     * The requested spread on the first line, the spread worth hunting on the second —
     * dropped when the two are the same. In Master League the best is always 15/15/15,
     * which says on its own that an uncapped league rewards nothing but raw IVs.
     */
}
