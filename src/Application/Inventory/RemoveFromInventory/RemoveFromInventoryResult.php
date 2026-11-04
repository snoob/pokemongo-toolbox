<?php

declare(strict_types=1);

namespace App\Application\Inventory\RemoveFromInventory;

use App\Domain\Inventory\Model\InventoryEntry;
use App\Domain\Pokemon\Model\Species;

/**
 * The entry removed; `species` is null when the game data no longer knows the form.
 */
final readonly class RemoveFromInventoryResult
{
    public function __construct(
        public InventoryEntry $entry,
        public ?Species $species,
    ) {}
}
