<?php

declare(strict_types=1);

namespace App\Infrastructure\Inventory;

use App\Domain\Inventory\Model\Account;
use App\Infrastructure\Environment\UserDirectory;

/**
 * Where each account's inventory is kept: one file per account, in a directory under the
 * XDG data one — the player's data, not a cache. `POGO_INVENTORY` names another directory.
 */
final readonly class InventoryFile
{
    /**
     * @param array<array-key, mixed> $server the process environment, as `$_SERVER` holds it
     */
    public static function directory(array $server): string
    {
        return UserDirectory::data($server, 'POGO_INVENTORY', 'inventory');
    }

    public static function in(string $directory, Account $account): string
    {
        return \sprintf('%s/%s.json', rtrim($directory, '/'), $account->name);
    }
}
