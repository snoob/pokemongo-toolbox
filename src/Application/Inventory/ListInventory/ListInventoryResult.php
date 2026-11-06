<?php

declare(strict_types=1);

namespace App\Application\Inventory\ListInventory;

use App\Domain\Inventory\Model\Account;

/**
 * Lines grouped by league, in the order of `League::cases()`, then best first: the form's
 * place in the league, then the IVs' place as that form.
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
    ) {}
}
