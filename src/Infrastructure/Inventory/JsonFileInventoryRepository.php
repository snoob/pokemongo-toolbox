<?php

declare(strict_types=1);

namespace App\Infrastructure\Inventory;

use App\Domain\Inventory\Model\Inventory;
use App\Domain\Inventory\Port\InventoryRepository;

/**
 * The inventory as a JSON file the player can read, back up or edit by hand.
 *
 * Unlike a cache, a file that cannot be read is an error, never an empty inventory: the
 * next save would otherwise wipe what the player kept. A save goes through a temporary
 * file and a rename, so an interrupted run leaves the previous file whole.
 */
final readonly class JsonFileInventoryRepository implements InventoryRepository
{
    /**
     * @param ?string $file null keeps it where `InventoryFile` says
     */
    public function __construct(
        private ?string $file = null,
    ) {}

    #[\Override]
    public function load(): Inventory
    {
        $file = $this->file();

        if (!is_file($file)) {
            return new Inventory();
        }

        $json = file_get_contents($file);

        try {
            return InventoryDocument::decode(false === $json ? '' : $json);
        } catch (\UnexpectedValueException $e) {
            throw new \RuntimeException(
                \sprintf(
                    'The inventory "%s" is unreadable (%s); fix or move it, nothing was changed.',
                    $file,
                    $e->getMessage(),
                ),
                previous: $e,
            );
        }
    }

    #[\Override]
    public function save(Inventory $inventory): void
    {
        $file = $this->file();
        $directory = \dirname($file);

        if (!is_dir($directory) && !mkdir($directory, 0o775, true) && !is_dir($directory)) {
            throw new \RuntimeException(\sprintf('Cannot create "%s" to keep the inventory.', $directory));
        }

        $temporary = $file . '.tmp';

        if (
            false === file_put_contents($temporary, InventoryDocument::encode($inventory))
            || !rename($temporary, $file)
        ) {
            throw new \RuntimeException(\sprintf('Cannot write the inventory to "%s".', $file));
        }
    }

    private function file(): string
    {
        return $this->file ?? InventoryFile::path($_SERVER);
    }
}
