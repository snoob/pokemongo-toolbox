<?php

declare(strict_types=1);

namespace App\UI\Cli\Presenter;

use App\Application\Inventory\AddToInventory\AddedEntry;
use App\Application\Inventory\AddToInventory\AddToInventoryResult;
use App\Domain\Pvp\Exception\EvolvesOverCap;
use App\Domain\Pvp\Exception\RankOutOfRange;
use App\Infrastructure\Naming\SpeciesNameResolver;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * What `inventory:add` kept, or why it refused a Pokémon its league cannot take.
 */
final readonly class InventoryAddTextPresenter
{
    public function __construct(
        private SpeciesNameResolver $names,
        private StandingFormatter $standings,
    ) {}

    public function refusal(RankOutOfRange|EvolvesOverCap $e, string $locale): string
    {
        $form = $this->names->name($e->form, $locale);

        if ($e instanceof EvolvesOverCap) {
            return \sprintf(
                'At CP %d it is level %s: as %s it would show %d CP, over the %s cap of %d. Nothing added.',
                $e->cp->value,
                $e->level,
                $form,
                $e->formCp->value,
                $e->league->label(),
                $e->league->cpCap() ?? 0,
            );
        }

        return null === $e->total
            ? \sprintf('%s cannot get under the %s CP cap: no rank to read.', $form, $e->league->label())
            : \sprintf(
                'Rank %d does not exist as %s in the %s: it ranks %d spreads.',
                $e->rank,
                $form,
                $e->league->label(),
                $e->total,
            );
    }

    public function present(SymfonyStyle $io, AddToInventoryResult $result, string $locale): void
    {
        $name = $this->names->name($result->species, $locale);

        if (null !== $result->guessedFrom) {
            $io->text(\sprintf('<comment>Interpreted "%s" as %s.</comment>', $result->guessedFrom, $name));
        }

        $io->newLine();
        $io->text($this->lines($result->added, $name, (string) $result->account, $locale));
        $io->newLine();
    }

    /**
     * @return list<string>
     */
    private function lines(AddedEntry $added, string $name, string $account, string $locale): array
    {
        $standing = $added->standing;
        $lines = [
            \sprintf(
                '<info>Kept #%d</info> %s %s for the <options=bold>%s</> <fg=gray>(%s)</>.',
                $added->entry->id,
                $name,
                $added->entry->iv,
                $added->entry->league->label(),
                $account,
            ),
            \sprintf(
                '  As %s: %s in the league — IVs %s',
                $this->standings->form($standing, $locale),
                $this->standings->speciesRank($standing->speciesRank),
                $this->standings->spread($standing->spread),
            ),
        ];

        if (null === $standing->spread) {
            return $lines;
        }

        $better = $added->better;
        $lines[] = null === $better
            ? '  <fg=green>Your best for that form in this league.</>'
            : \sprintf(
                '  <comment>You already keep better: #%d %s %s, IVs #%d as %s.</comment>',
                $better->entry->id,
                $this->names->name($better->species, $locale),
                $better->entry->iv,
                $better->spread->position,
                $this->standings->form($standing, $locale),
            );

        return $lines;
    }
}
