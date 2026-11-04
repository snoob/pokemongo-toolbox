<?php

declare(strict_types=1);

namespace App\Application\Inventory\ListInventory;

use App\Application\Inventory\InventoryRanker;
use App\Domain\Inventory\Model\InventoryEntry;
use App\Domain\Inventory\Port\InventoryRepository;
use App\Domain\Pokemon\Port\SpeciesCatalog;
use App\Domain\Pvp\Model\League;

final readonly class ListInventoryHandler
{
    public function __construct(
        private InventoryRepository $repository,
        private SpeciesCatalog $catalog,
        private InventoryRanker $ranker,
    ) {}

    public function __invoke(ListInventoryQuery $query): ListInventoryResult
    {
        $lines = array_map($this->line(...), $this->repository->load()->entries($query->league));

        usort($lines, static fn(InventoryLine $a, InventoryLine $b): int => self::sortKey($a) <=> self::sortKey($b));

        return new ListInventoryResult($lines);
    }

    private function line(InventoryEntry $entry): InventoryLine
    {
        $species = $this->catalog->find($entry->species);

        return new InventoryLine(
            $entry,
            $species,
            null === $species ? null : $this->ranker->standing($species, $entry->iv, $entry->league),
        );
    }

    /**
     * Unranked forms and spreads that bust the cap sink to the bottom of their league.
     *
     * @return list<int>
     */
    private static function sortKey(InventoryLine $line): array
    {
        return [
            (int) array_search($line->entry->league, League::cases(), true),
            $line->standing->speciesRank->position ?? \PHP_INT_MAX,
            $line->standing->spread->position ?? \PHP_INT_MAX,
            $line->entry->id,
        ];
    }
}
