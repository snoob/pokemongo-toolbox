<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Exception;

use App\Domain\Inventory\Model\InventoryEntry;

/**
 * The same form with the same IVs is already kept for that league: most likely the same
 * Pokémon typed twice.
 */
final class DuplicateEntry extends \DomainException
{
    private function __construct(
        string $message,
        public readonly InventoryEntry $existing,
    ) {
        parent::__construct($message);
    }

    public static function of(InventoryEntry $existing): self
    {
        return new self(
            \sprintf(
                '%s %s is already kept for the %s as #%d.',
                $existing->species,
                $existing->iv,
                $existing->league->label(),
                $existing->id,
            ),
            $existing,
        );
    }
}
