<?php

declare(strict_types=1);

namespace App\UI\Cli\Presenter;

use App\Application\Inventory\ListInventory\InventoryLine;
use App\Application\Inventory\ListInventory\ListInventoryResult;
use App\Domain\Pokemon\Model\Species;
use App\Infrastructure\Naming\SpeciesNameResolver;

final readonly class InventoryJsonPresenter
{
    public function __construct(
        private SpeciesNameResolver $names,
    ) {}

    public function present(ListInventoryResult $result, ?string $locale = null): string
    {
        return json_encode(
            array_map(
                /** @return array<string, mixed> */
                fn(InventoryLine $line): array => ['account' => $result->account->name, ...$this->line($line, $locale)],
                $result->lines,
            ),
            \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function line(InventoryLine $line, ?string $locale): array
    {
        $standing = $line->standing;
        $spread = $standing?->spread;

        return [
            'id' => $line->entry->id,
            'league' => $line->entry->league->value,
            'species' => $this->species($line->species, $line->entry->species->value, $locale),
            'iv' => (string) $line->entry->iv,
            'addedAt' => $line->entry->addedAt->format(\DATE_ATOM),
            'rankedAs' => null === $standing
                ? null
                : $this->species($standing->form, $standing->form->id->value, $locale),
            'leagueRank' => null === $standing?->speciesRank
                ? null
                : ['position' => $standing->speciesRank->position, 'total' => $standing->speciesRank->total],
            'ivRank' => null === $spread
                ? null
                : [
                    'position' => $spread->position,
                    'total' => $spread->total,
                    'percentOfBest' => round($spread->percentOfBest, 2),
                    'cp' => $spread->cp->value,
                    'level' => $spread->level->value,
                ],
        ];
    }

    /**
     * @return array{id: string, name: ?string}
     */
    private function species(?Species $species, string $id, ?string $locale): array
    {
        return ['id' => $id, 'name' => null === $species ? null : $this->names->name($species, $locale)];
    }
}
