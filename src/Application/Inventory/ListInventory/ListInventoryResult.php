<?php

declare(strict_types=1);

namespace App\Application\Inventory\ListInventory;

use App\Domain\Inventory\Model\Account;
use App\Domain\Pokemon\Model\Species;

/**
 * Lines grouped by league, in the order of `League::cases()`, then best first: the form's
 * place in the league, then the IVs' place as that form (`InventoryLine::rankOrder()`).
 *
 * `species` is the Pokémon the query named, `guessedFrom` the name as typed when the
 * nearest one was taken instead.
 */
final readonly class ListInventoryResult
{
    /**
     * @param list<InventoryLine> $lines
     */
    public function __construct(
        /** @var list<InventoryLine> */
        public array $lines,
        public Account $account,
        public ?Species $species = null,
        public ?string $guessedFrom = null,
    ) {}
}
