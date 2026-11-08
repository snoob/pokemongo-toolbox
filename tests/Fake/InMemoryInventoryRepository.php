<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use App\Domain\Inventory\Model\Account;
use App\Domain\Inventory\Model\Inventory;
use App\Domain\Inventory\Port\InventoryRepository;

/**
 * Keeps a copy per account, as a file would: what the caller changes after a save is not
 * saved.
 */
final class InMemoryInventoryRepository implements InventoryRepository
{
    public int $saves = 0;

    /** @var array<string, Inventory> */
    private array $stored = [];

    #[\Override]
    public function load(Account $account): Inventory
    {
        $stored = $this->stored[$account->name] ?? null;

        return null === $stored ? new Inventory() : clone $stored;
    }

    #[\Override]
    public function save(Account $account, Inventory $inventory): void
    {
        $this->stored[$account->name] = clone $inventory;
        ksort($this->stored);
        ++$this->saves;
    }

    #[\Override]
    public function accounts(): array
    {
        return array_map(static fn(string $name): Account => new Account($name), array_keys($this->stored));
    }
}
