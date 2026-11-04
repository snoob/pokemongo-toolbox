<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure;

use App\Infrastructure\Inventory\InventoryFile;
use PHPUnit\Framework\TestCase;

final class InventoryFileTest extends TestCase
{
    public function testAnExplicitPathWins(): void
    {
        self::assertSame('/tmp/mine.json', InventoryFile::path([
            'POGO_INVENTORY' => '/tmp/mine.json',
            'XDG_DATA_HOME' => '/xdg',
            'HOME' => '/home/me',
        ]));
    }

    public function testItFollowsTheXdgDataDirectory(): void
    {
        self::assertSame('/xdg/pokemongo-toolbox/inventory.json', InventoryFile::path([
            'POGO_INVENTORY' => '',
            'XDG_DATA_HOME' => '/xdg',
            'HOME' => '/home/me',
        ]));
    }

    public function testItFallsBackOnTheHomeDataDirectory(): void
    {
        self::assertSame('/home/me/.local/share/pokemongo-toolbox/inventory.json', InventoryFile::path([
            'POGO_INVENTORY' => '',
            'XDG_DATA_HOME' => '',
            'HOME' => '/home/me',
        ]));
    }
}
