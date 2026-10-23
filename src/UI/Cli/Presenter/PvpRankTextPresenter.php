<?php

declare(strict_types=1);

namespace App\UI\Cli\Presenter;

use App\Application\Pvp\RankPokemon\EvolutionRanking;
use App\Application\Pvp\RankPokemon\LeagueOutcome;
use App\Application\Pvp\RankPokemon\RankPokemonResult;
use App\Domain\Pokemon\Model\Species;
use App\Domain\Pvp\Model\MegaLevel;
use App\Infrastructure\Naming\SpeciesNameResolver;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * One table per form of the family, the asked one first, then the notes they share.
 */
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
        // Said before the tables: a wrong guess must show before it is read as an answer.
        if (null !== $result->guessedFrom) {
            $io->text(\sprintf(
                '<comment>Interpreted "%s" as %s.</comment>',
                $result->guessedFrom,
                $this->names->name($result->asked, $locale),
            ));
        }

        foreach ($result->evolutions as $ranking) {
            $this->table($io, $ranking, $result, $locale);
        }

        foreach ($this->notes->of($result, $locale) as $note) {
            $io->text(\sprintf('<comment>%s</comment>', $note));
        }
    }

    private function table(
        SymfonyStyle $io,
        EvolutionRanking $ranking,
        RankPokemonResult $result,
        ?string $locale,
    ): void {
        $species = $ranking->species();

        $io->title($this->title($species, $result->megaLevel, $locale));

        // The power-up column only earns its width once the form in hand has to evolve.
        $showsBaseForm = $this->baseForms->appliesTo($ranking);
        $rows = [];

        foreach ($ranking->outcomes as $outcome) {
            $row = [
                $outcome->league->label(),
                $this->speciesRankCell($outcome, $species, $locale),
                $this->ivCells->format($outcome, $result->iv, $result->floor),
            ];

            if ($showsBaseForm) {
                $row[] = $this->baseForms->format($outcome);
            }

            $rows[] = $row;
        }

        $headers = ['League', 'Species rank', 'IVs'];

        if ($showsBaseForm) {
            $headers[] = $this->baseForms->header($result->asked, $locale);
        }

        $io->table($headers, $rows);
    }

    /**
     * The form ranked in the table and its Pokédex number — the form in hand is already
     * named by the power-up column. A mega also says which Mega Level it is ranked at,
     * since that level moves the CP to stop at: "Malamar (Mega, level 3)".
     */
    private function title(Species $species, ?MegaLevel $megaLevel, ?string $locale): string
    {
        $name = $this->names->name($species, $locale);

        if ($species->isMega() && null !== $megaLevel && str_ends_with($name, ')')) {
            $name = \sprintf('%s, level %d)', substr($name, 0, -1), $megaLevel->value);
        }

        return \sprintf('%s — #%d', $name, $species->dex->value);
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
}
