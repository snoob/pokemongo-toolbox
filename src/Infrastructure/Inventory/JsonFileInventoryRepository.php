<?php

declare(strict_types=1);

namespace App\Infrastructure\Inventory;

use App\Domain\Inventory\Model\Account;
use App\Domain\Inventory\Model\Inventory;
use App\Domain\Inventory\Port\InventoryRepository;
use App\Infrastructure\Json\JsonDocumentFile;

/**
 * Each account's inventory as a JSON file the player can read, back up or edit by hand;
 * `JsonDocumentFile` keeps it safe to read and to write.
 */
final readonly class JsonFileInventoryRepository implements InventoryRepository
{
    /**
     * @param ?string $directory null keeps them where `InventoryFile` says
     */
    public function __construct(
        private ?string $directory = null,
    ) {}

    #[\Override]
    public function load(Account $account): Inventory
    {
        return (
            JsonDocumentFile::read(
                $this->file($account),
                'inventory',
                InventoryDocument::decode(...),
            ) ?? new Inventory()
        );
    }

    #[\Override]
    public function save(Account $account, Inventory $inventory): void
    {
        JsonDocumentFile::write($this->file($account), 'inventory', InventoryDocument::encode($inventory));
    }

    private function file(Account $account): string
    {
        return InventoryFile::in($this->directory ?? InventoryFile::directory($_SERVER), $account);
    }
}
