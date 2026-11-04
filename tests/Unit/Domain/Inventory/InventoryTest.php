<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Inventory;

use App\Domain\Inventory\Exception\DuplicateEntry;
use App\Domain\Inventory\Exception\EntryNotFound;
use App\Domain\Inventory\Model\Inventory;
use App\Domain\Inventory\Model\InventoryEntry;
use App\Domain\Pokemon\Model\IvSpread;
use App\Domain\Pokemon\Model\SpeciesId;
use App\Domain\Pvp\Model\League;
use PHPUnit\Framework\TestCase;

final class InventoryTest extends TestCase
{
    public function testItKeepsOneEntryPerLeague(): void
    {
        $inventory = new Inventory();

        $added = $inventory->add(
            new SpeciesId('dratini'),
            new IvSpread(0, 15, 15),
            [League::Great, League::Ultra],
            $this->now(),
        );

        self::assertSame([1, 2], array_map(static fn(InventoryEntry $e): int => $e->id, $added));
        self::assertCount(1, $inventory->entries(League::Great));
        self::assertCount(1, $inventory->entries(League::Ultra));
        self::assertSame([], $inventory->entries(League::MegaGreat), 'a Mega Edition is a league of its own');
    }

    public function testALeagueGivenTwiceKeepsOneEntry(): void
    {
        $inventory = new Inventory();

        $inventory->add(new SpeciesId('gengar'), new IvSpread(1, 15, 14), [League::Great, League::Great], $this->now());

        self::assertCount(1, $inventory->entries());
    }

    public function testTheSamePokemonTwiceInALeagueAddsNothing(): void
    {
        $inventory = new Inventory();
        $inventory->add(new SpeciesId('gengar'), new IvSpread(1, 15, 14), [League::Great], $this->now());

        try {
            $inventory->add(
                new SpeciesId('gengar'),
                new IvSpread(1, 15, 14),
                [League::Ultra, League::Great],
                $this->now(),
            );
            self::fail('a duplicate must be refused');
        } catch (DuplicateEntry $e) {
            self::assertSame(1, $e->existing->id);
        }

        self::assertCount(1, $inventory->entries(), 'all or none: the Ultra League entry is not added either');
    }

    public function testTheSameIvsAsAnotherFormAreAnotherPokemon(): void
    {
        $inventory = new Inventory();
        $inventory->add(new SpeciesId('gengar'), new IvSpread(1, 15, 14), [League::Great], $this->now());
        $inventory->add(new SpeciesId('gengar_shadow'), new IvSpread(1, 15, 14), [League::Great], $this->now());

        self::assertCount(2, $inventory->entries());
    }

    public function testARemovedNumberIsNeverGivenAgain(): void
    {
        $inventory = new Inventory();
        $inventory->add(new SpeciesId('gengar'), new IvSpread(1, 15, 14), [League::Great, League::Ultra], $this->now());

        $removed = $inventory->remove(2);
        $added = $inventory->add(new SpeciesId('gastly'), new IvSpread(0, 15, 15), [League::Great], $this->now());

        self::assertSame(2, $removed->id);
        self::assertSame(3, $added[0]->id);
    }

    public function testRemovingAnUnknownNumberSaysSo(): void
    {
        $this->expectException(EntryNotFound::class);

        new Inventory()->remove(7);
    }

    public function testTheNextNumberFollowsTheEntriesItIsRestoredWith(): void
    {
        $inventory = new Inventory();
        $inventory->add(new SpeciesId('gengar'), new IvSpread(1, 15, 14), [League::Great], $this->now());

        self::assertSame(5, new Inventory($inventory->entries(), 5)->nextId());
        self::assertSame(2, new Inventory($inventory->entries(), 1)->nextId(), 'never below the highest id + 1');
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-11-04 12:00:00');
    }
}
