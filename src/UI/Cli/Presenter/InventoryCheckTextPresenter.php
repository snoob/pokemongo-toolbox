<?php

declare(strict_types=1);

namespace App\UI\Cli\Presenter;

use App\Application\Inventory\CheckInventory\CheckedPokemon;
use App\Application\Inventory\CheckInventory\CheckInventoryResult;
use App\Infrastructure\Naming\SpeciesNameResolver;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * The kept Pokémon a trade can still improve, a table per league, then on one line those
 * no trade can beat — they are done — and those that cannot be weighed.
 */
final readonly class InventoryCheckTextPresenter
{
    public function __construct(
        private SpeciesNameResolver $names,
        private StandingFormatter $standings,
    ) {}

    /**
     * @param list<CheckedPokemon> $checked in the order shown
     */
    public function present(SymfonyStyle $io, CheckInventoryResult $result, array $checked, ?string $locale): void
    {
        $io->text(\sprintf(
            'Account <options=bold>%s</> — trade floor <options=bold>%s</>',
            $result->account,
            $result->floor,
        ));

        if ([] === $checked) {
            $io->text('Nothing kept in these leagues; "inventory:add" keeps a Pokémon for a league.');

            return;
        }

        $improvable = array_filter($checked, static fn(CheckedPokemon $c): bool => $c->odds?->canBeBeaten() ?? false);
        $done = array_filter($checked, static fn(CheckedPokemon $c): bool => false === $c->odds?->canBeBeaten());
        $unweighed = array_filter($checked, static fn(CheckedPokemon $c): bool => null === $c->odds);

        $this->tables($io, array_values($improvable), $locale);

        if ([] === $improvable) {
            $io->newLine();
            $io->text('<fg=green>No trade can improve anything kept here.</>');
        }

        foreach ($this->byLeague($done) as $league => $kept) {
            $io->text(\sprintf('<fg=green>No trade can beat</> — %s: %s', $league, $this->listed($kept, $locale)));
        }

        foreach ($this->byLeague($unweighed) as $league => $kept) {
            $io->text(\sprintf(
                '<fg=gray>Cannot be weighed (unknown form, or over the CP cap)</> — %s: %s',
                $league,
                $this->listed($kept, $locale),
            ));
        }

        $io->newLine();
    }

    /**
     * @param list<CheckedPokemon> $improvable
     */
    private function tables(SymfonyStyle $io, array $improvable, ?string $locale): void
    {
        foreach ($this->byLeague($improvable) as $label => $rows) {
            $io->section(\sprintf('%s — a trade can do better', $label));
            $io->table(
                ['Pokémon', 'IVs', 'Ranked as', 'IV rank', 'A trade beats it', 'Best a trade gives'],
                array_map(
                    /** @return list<string> */
                    fn(CheckedPokemon $c): array => $this->row($c, $locale),
                    $rows,
                ),
            );
        }
    }

    /**
     * @return list<string>
     */
    private function row(CheckedPokemon $checked, ?string $locale): array
    {
        $line = $checked->line;
        $odds = $checked->odds;
        $standing = $line->standing;
        $name = $this->name($checked, $locale);
        $form = null === $standing ? '' : $this->standings->form($standing, $locale);

        return [
            $name,
            (string) $line->entry->iv,
            $form === $name ? '<fg=gray>itself</>' : $form,
            null === $standing?->spread ? '' : \sprintf('#%d', $standing->spread->position),
            null === $odds
                ? ''
                : \sprintf('%s (%d / %d)', self::percent($odds->chance()), $odds->beatenBy, $odds->total),
            null === $odds ? '' : \sprintf('%s (#%d)', $odds->best->iv, $odds->best->position),
        ];
    }

    /**
     * @param array<CheckedPokemon> $checked in the order shown
     *
     * @return array<string, list<CheckedPokemon>> by league label, in that order
     */
    private function byLeague(array $checked): array
    {
        $byLeague = [];

        foreach ($checked as $one) {
            $byLeague[$one->line->entry->league->label()][] = $one;
        }

        return $byLeague;
    }

    /**
     * Below 1 %, one decimal: "0.8 %" says more than "1 %" when trades cost Stardust.
     */
    private static function percent(float $chance): string
    {
        $percent = $chance * 100;

        return $percent < 1.0 ? \sprintf('%.1f %%', $percent) : \sprintf('%d %%', (int) round($percent));
    }

    /**
     * @param array<CheckedPokemon> $checked
     */
    private function listed(array $checked, ?string $locale): string
    {
        return implode(', ', array_map(fn(CheckedPokemon $c): string => \sprintf(
            '%s %s',
            $this->name($c, $locale),
            $c->line->entry->iv,
        ), $checked));
    }

    private function name(CheckedPokemon $checked, ?string $locale): string
    {
        $line = $checked->line;

        return null === $line->species ? $line->entry->species->value : $this->names->name($line->species, $locale);
    }
}
