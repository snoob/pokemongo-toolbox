<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use App\Domain\Inventory\Model\Inventory;
use App\Domain\Inventory\Port\InventoryRepository;

/**
 * Keeps a copy, as a file would: what the caller changes after a save is not saved.
 */
final class InMemoryInventoryRepository implements InventoryRepository
{
    public int $saves = 0;

    private Inventory $stored;

    public function __construct(?Inventory $inventory = null)
    {
        $this->stored = $inventory ?? new Inventory();
    }

    #[\Override]
    public function load(): Inventory
    {
        return clone $this->stored;
    }

    #[\Override]
    public function save(Inventory $inventory): void
    {
        $this->stored = clone $inventory;
        ++$this->saves;
    }
}
