<?php

declare(strict_types=1);

namespace App\UI\Cli\Presenter;

use App\Application\Pvp\RankPokemon\LeagueOutcome;
use App\Application\Pvp\RankPokemon\RankPokemonResult;
use App\Domain\Pvp\Model\League;
use App\Infrastructure\Naming\SpeciesNameResolver;
use Symfony\Component\Console\Style\SymfonyStyle;

final readonly class PvpRankTextPresenter
{
    public function __construct(
        private SpeciesNameResolver $names,
    ) {}

    public function present(SymfonyStyle $io, RankPokemonResult $result, ?string $locale = null): void
    {
        $species = $result->species;

        $io->title(\sprintf('%s — #%d', $this->names->name($species, $locale), $species->dex->value));

        $rows = [];

        foreach ($result->outcomes as $outcome) {
            $rows[] = [$outcome->league->label(), $this->speciesRankCell($outcome), $this->spreadCell($outcome)];
        }

        $io->table([
            'Ligue',
            "Rang de l'espèce",
            null !== $result->iv ? \sprintf('IVs %s', $result->iv) : 'IVs',
        ], $rows);

        if (null !== $result->iv) {
            $io->text(\sprintf('<comment>Niveau maximum considéré : %s.</comment>', $result->levelCap));
        }

        if ($this->showsMasterCaveat($result)) {
            $io->text(
                "<comment>En Master League aucun plafond de CP ne s'applique : classer les IVs revient à trier les IVs bruts, 15/15/15 gagne toujours.</comment>",
            );
        }
    }

    private function showsMasterCaveat(RankPokemonResult $result): bool
    {
        foreach ($result->outcomes as $outcome) {
            if (League::Master === $outcome->league && null !== $outcome->spread) {
                return true;
            }
        }

        return false;
    }

    private function speciesRankCell(LeagueOutcome $outcome): string
    {
        $rank = $outcome->speciesRank;

        if (null === $rank) {
            return '<fg=gray>non classé</>';
        }

        return \sprintf('#%d / %d  (score %.1f)', $rank->position, $rank->total, $rank->score);
    }

    private function spreadCell(LeagueOutcome $outcome): string
    {
        if ($outcome->ineligible) {
            return '<fg=gray>inéligible (CP trop élevé)</>';
        }

        $spread = $outcome->spread;

        if (null === $spread) {
            return '<fg=gray>—</>';
        }

        return \sprintf(
            '#%d / %d — %.2f %% — CP %d au niveau %s',
            $spread->position,
            $spread->total,
            $spread->percentOfBest,
            $spread->cp->value,
            $spread->level,
        );
    }
}
