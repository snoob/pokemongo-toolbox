<?php

declare(strict_types=1);

namespace App\UI\Cli\Presenter;

use App\Application\Inventory\ListInventory\InventoryLine;
use App\Application\Inventory\ListInventory\ListInventoryResult;
use App\Infrastructure\Naming\SpeciesNameResolver;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * One table per league, best first. The form column names what the league ranks the
 * Pokémon as, which is not always its last evolution.
 */
final readonly class InventoryTextPresenter
{
    public function __construct(
        private SpeciesNameResolver $names,
        private StandingFormatter $standings,
    ) {}

    public function present(SymfonyStyle $io, ListInventoryResult $result, ?string $locale = null): void
    {
        $io->text(\sprintf('Account <options=bold>%s</>', $result->account));

        if ([] === $result->lines) {
            $io->text('Nothing kept yet; "inventory:add" keeps a Pokémon for a league.');

            return;
        }

        $byLeague = [];

        foreach ($result->lines as $line) {
            $byLeague[$line->entry->league->label()][] = $line;
        }

        foreach ($byLeague as $label => $lines) {
            $io->section($label);
            $io->table(
                ['Pokémon', 'IVs', 'Ranked as', 'League rank', 'IV rank'],
                array_map(
                    /** @return list<string> */
                    fn(InventoryLine $line): array => $this->row($line, $locale),
                    $lines,
                ),
            );
        }
    }

    /**
     * @return list<string>
     */
    private function row(InventoryLine $line, ?string $locale): array
    {
        $entry = $line->entry;

        if (null === $line->species || null === $line->standing) {
            return [
                \sprintf('<comment>%s — unknown to the game data</comment>', $entry->species),
                (string) $entry->iv,
                '',
                '',
                '',
            ];
        }

        $name = $this->names->name($line->species, $locale);
        $form = $this->standings->form($line->standing, $locale);

        return [
            $name,
            (string) $entry->iv,
            $form === $name ? '<fg=gray>itself</>' : $form,
            $this->standings->speciesRank($line->standing->speciesRank),
            $this->standings->spread($line->standing->spread),
        ];
    }
}
