<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure;

use App\Domain\Inventory\Model\Account;
use App\Infrastructure\Inventory\InventoryFile;
use PHPUnit\Framework\TestCase;

final class InventoryFileTest extends TestCase
{
    public function testAnExplicitDirectoryWins(): void
    {
        self::assertSame('/tmp/mine', InventoryFile::directory([
            'POGO_INVENTORY' => '/tmp/mine',
            'XDG_DATA_HOME' => '/xdg',
            'HOME' => '/home/me',
        ]));
    }

    public function testItFollowsTheXdgDataDirectory(): void
    {
        self::assertSame('/xdg/pokemongo-toolbox/inventory', InventoryFile::directory([
            'POGO_INVENTORY' => '',
            'XDG_DATA_HOME' => '/xdg',
            'HOME' => '/home/me',
        ]));
    }

    public function testItFallsBackOnTheHomeDataDirectory(): void
    {
        self::assertSame('/home/me/.local/share/pokemongo-toolbox/inventory', InventoryFile::directory([
            'POGO_INVENTORY' => '',
            'XDG_DATA_HOME' => '',
            'HOME' => '/home/me',
        ]));
    }

    public function testOneFilePerAccount(): void
    {
        self::assertSame('/data/snoob2555.json', InventoryFile::in('/data/', new Account('Snoob2555')));
    }
}
