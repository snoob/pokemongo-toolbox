<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Domain\Inventory\Model\Account;
use App\Domain\Inventory\Model\Inventory;
use App\Domain\Pokemon\Model\IvSpread;
use App\Domain\Pokemon\Model\SpeciesId;
use App\Domain\Pvp\Model\League;
use App\Infrastructure\Inventory\JsonFileInventoryRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class JsonFileInventoryRepositoryTest extends TestCase
{
    private string $directory;
    private string $file;

    #[\Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/pogo-inventory-' . bin2hex(random_bytes(6));
        $this->file = $this->directory . '/nested/snoob2555.json';
    }

    #[\Override]
    protected function tearDown(): void
    {
        new Filesystem()->remove($this->directory);
    }

    public function testNoFileYetIsAnEmptyInventory(): void
    {
        self::assertSame(
            [],
            new JsonFileInventoryRepository(\dirname($this->file))
                ->load(new Account('snoob2555'))
                ->entries(),
        );
    }

    public function testWhatIsSavedIsLoadedBackWithItsNumbering(): void
    {
        $repository = new JsonFileInventoryRepository(\dirname($this->file));
        $inventory = new Inventory();
        $at = new \DateTimeImmutable('2026-11-04T12:00:00+01:00');
        $inventory->add(new SpeciesId('gengar_shadow'), new IvSpread(1, 15, 14), League::Great, $at);
        $inventory->add(new SpeciesId('gengar_shadow'), new IvSpread(1, 15, 14), League::MegaGreat, $at);
        $inventory->remove(2);

        $repository->save(new Account('Snoob2555'), $inventory);
        $loaded = $repository->load(new Account('snoob2555'));
        $entry = $loaded->entries()[0] ?? null;

        self::assertNotNull($entry);
        self::assertSame(1, $entry->id);
        self::assertSame('gengar_shadow', $entry->species->value);
        self::assertSame('1/15/14', (string) $entry->iv);
        self::assertSame(League::Great, $entry->league);
        self::assertEquals($at, $entry->addedAt);
        self::assertSame(3, $loaded->nextId(), 'the removed number stays used');
        $files = scandir(\dirname($this->file));
        self::assertIsArray($files);
        self::assertSame(
            ['snoob2555.json'],
            array_values(array_diff($files, ['.', '..'])),
            'no temporary file left behind',
        );
        self::assertSame([], $repository->load(Account::default())->entries(), 'each account has its own');
    }

    public function testAMissingDirectoryIsCreatedForTheOwnerAndTheGroupOnly(): void
    {
        $umask = umask(0);

        try {
            new JsonFileInventoryRepository(\dirname($this->file))->save(Account::default(), new Inventory());
        } finally {
            umask($umask);
        }

        self::assertSame(0o775, fileperms(\dirname($this->file)) & 0o777, 'never writable by others');
    }

    public function testAnUnreadableFileIsAnErrorAndIsLeftAsItIs(): void
    {
        mkdir(\dirname($this->file), 0o775, true);
        file_put_contents($this->file, '{"version": 1, "entries": [');

        try {
            new JsonFileInventoryRepository(\dirname($this->file))->load(new Account('snoob2555'));
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

        new JsonFileInventoryRepository(\dirname($this->file))->load(new Account('snoob2555'));
    }

    public function testAnotherVersionIsAnError(): void
    {
        mkdir(\dirname($this->file), 0o775, true);
        file_put_contents($this->file, '{"version": 2, "entries": []}');

        $this->expectException(\RuntimeException::class);

        new JsonFileInventoryRepository(\dirname($this->file))->load(new Account('snoob2555'));
    }
}
