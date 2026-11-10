<?php

declare(strict_types=1);

namespace App\Application\Inventory\ListInventory;

use App\Application\Inventory\InventoryRanker;
use App\Domain\Inventory\Model\InventoryEntry;
use App\Domain\Inventory\Port\InventoryRepository;
use App\Domain\Pokemon\Port\SpeciesCatalog;

final readonly class ListInventoryHandler
{
    public function __construct(
        private InventoryRepository $repository,
        private SpeciesCatalog $catalog,
        private InventoryRanker $ranker,
    ) {}

    public function __invoke(ListInventoryQuery $query): ListInventoryResult
    {
        $lines = array_map($this->line(...), $this->repository->load($query->account)->entries($query->league));

        // A stable sort: equal ranks keep the order they were added in. The display breaks
        // those ties by name, which only it knows in the language shown.
        usort($lines, static fn(InventoryLine $a, InventoryLine $b): int => $a->rankOrder() <=> $b->rankOrder());

        return new ListInventoryResult($lines, $query->account);
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
}
