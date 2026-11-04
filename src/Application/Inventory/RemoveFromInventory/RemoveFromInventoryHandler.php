<?php

declare(strict_types=1);

namespace App\Application\Inventory\RemoveFromInventory;

use App\Domain\Inventory\Exception\EntryNotFound;
use App\Domain\Inventory\Port\InventoryRepository;
use App\Domain\Pokemon\Port\SpeciesCatalog;

final readonly class RemoveFromInventoryHandler
{
    public function __construct(
        private InventoryRepository $repository,
        private SpeciesCatalog $catalog,
    ) {}

    /**
     * @throws EntryNotFound
     */
    public function __invoke(RemoveFromInventoryCommand $command): RemoveFromInventoryResult
    {
        $inventory = $this->repository->load();
        $removed = $inventory->remove($command->id);
        $this->repository->save($inventory);

        return new RemoveFromInventoryResult($removed, $this->catalog->find($removed->species));
    }
}
