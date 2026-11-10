<?php

declare(strict_types=1);

namespace App\Application\Inventory\AddToInventory;

use App\Domain\Inventory\Model\Account;
use App\Domain\Pokemon\Model\Species;

/**
 * `guessedFrom` holds the identifier as typed when the nearest name was taken instead.
 */
final readonly class AddToInventoryResult
{
    public function __construct(
        public Species $species,
        public AddedEntry $added,
        public Account $account,
        public ?string $guessedFrom = null,
    ) {}
}
