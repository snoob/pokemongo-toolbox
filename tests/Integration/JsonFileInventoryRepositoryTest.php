<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Domain\Inventory\Model\Inventory;
use App\Domain\Pokemon\Model\IvSpread;
use App\Domain\Pokemon\Model\SpeciesId;
use App\Domain\Pvp\Model\League;
use App\Infrastructure\Inventory\JsonFileInventoryRepository;
use PHPUnit\Framework\TestCase;

final class JsonFileInventoryRepositoryTest extends TestCase
{
    private string $directory;
    private string $file;

    #[\Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/pogo-inventory-' . bin2hex(random_bytes(6));
        $this->file = $this->directory . '/nested/inventory.json';
    }

    #[\Override]
    protected function tearDown(): void
    {
        array_map(unlink(...), array_filter([$this->file, $this->file . '.tmp'], is_file(...)));
        array_map(rmdir(...), array_filter([\dirname($this->file), $this->directory], is_dir(...)));
    }

    public function testNoFileYetIsAnEmptyInventory(): void
    {
        self::assertSame([], new JsonFileInventoryRepository($this->file)->load()->entries());
    }

    public function testWhatIsSavedIsLoadedBackWithItsNumbering(): void
    {
        $repository = new JsonFileInventoryRepository($this->file);
        $inventory = new Inventory();
        $at = new \DateTimeImmutable('2026-11-04T12:00:00+01:00');
        $inventory->add(
            new SpeciesId('gengar_shadow'),
            new IvSpread(1, 15, 14),
            [League::Great, League::MegaGreat],
            $at,
        );
        $inventory->remove(2);

        $repository->save($inventory);
        $loaded = $repository->load();
        $entry = $loaded->entries()[0] ?? null;

        self::assertNotNull($entry);
        self::assertSame(1, $entry->id);
        self::assertSame('gengar_shadow', $entry->species->value);
        self::assertSame('1/15/14', (string) $entry->iv);
        self::assertSame(League::Great, $entry->league);
        self::assertEquals($at, $entry->addedAt);
        self::assertSame(3, $loaded->nextId(), 'the removed number stays used');
        self::assertFileDoesNotExist($this->file . '.tmp');
    }

    public function testAnUnreadableFileIsAnErrorAndIsLeftAsItIs(): void
    {
        mkdir(\dirname($this->file), 0o775, true);
        file_put_contents($this->file, '{"version": 1, "entries": [');

        try {
            new JsonFileInventoryRepository($this->file)->load();
            self::fail('a broken file must not read as an empty inventory');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('unreadable', $e->getMessage());
        }

        self::assertSame('{"version": 1, "entries": [', file_get_contents($this->file));
    }

    public function testAMalformedEntryIsAnError(): void
    {
        mkdir(\dirname($this->file), 0o775, true);
        file_put_contents($this->file, json_encode([
            'version' => 1,
            'nextId' => 2,
            'entries' => [[
                'id' => 1,
                'species' => 'gengar',
                'iv' => '1/15/99',
                'league' => 'great',
                'addedAt' => 'now',
            ]],
        ]));

        $this->expectException(\RuntimeException::class);

        new JsonFileInventoryRepository($this->file)->load();
    }

    public function testAnotherVersionIsAnError(): void
    {
        mkdir(\dirname($this->file), 0o775, true);
        file_put_contents($this->file, '{"version": 2, "entries": []}');

        $this->expectException(\RuntimeException::class);

        new JsonFileInventoryRepository($this->file)->load();
    }
}
