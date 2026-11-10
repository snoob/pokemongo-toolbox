<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Inventory;

use App\Domain\Inventory\Exception\DuplicateEntry;
use App\Domain\Inventory\Exception\EntryNotFound;
use App\Domain\Inventory\Model\Inventory;
use App\Domain\Pokemon\Model\IvSpread;
use App\Domain\Pokemon\Model\SpeciesId;
use App\Domain\Pvp\Model\League;
use PHPUnit\Framework\TestCase;

final class InventoryTest extends TestCase
{
    public function testTheSamePokemonCanBeKeptForTwoLeagues(): void
    {
        $inventory = new Inventory();

        $great = $inventory->add(new SpeciesId('dratini'), new IvSpread(0, 15, 15), League::Great, $this->now());
        $ultra = $inventory->add(new SpeciesId('dratini'), new IvSpread(0, 15, 15), League::Ultra, $this->now());

        self::assertSame([1, 2], [$great->id, $ultra->id]);
        self::assertCount(1, $inventory->entries(League::Great));
        self::assertCount(1, $inventory->entries(League::Ultra));
        self::assertSame([], $inventory->entries(League::MegaGreat), 'a Mega Edition is a league of its own');
    }

    public function testTheSamePokemonTwiceInALeagueAddsNothing(): void
    {
        $inventory = new Inventory();
        $inventory->add(new SpeciesId('gengar'), new IvSpread(1, 15, 14), League::Great, $this->now());

        try {
            $inventory->add(new SpeciesId('gengar'), new IvSpread(1, 15, 14), League::Great, $this->now());
            self::fail('a duplicate must be refused');
        } catch (DuplicateEntry $e) {
            self::assertSame(1, $e->existing->id);
        }

        self::assertCount(1, $inventory->entries());
    }

    public function testTheSameIvsAsAnotherFormAreAnotherPokemon(): void
    {
        $inventory = new Inventory();
        $inventory->add(new SpeciesId('gengar'), new IvSpread(1, 15, 14), League::Great, $this->now());
        $inventory->add(new SpeciesId('gengar_shadow'), new IvSpread(1, 15, 14), League::Great, $this->now());

        self::assertCount(2, $inventory->entries());
    }

    public function testARemovedNumberIsNeverGivenAgain(): void
    {
        $inventory = new Inventory();
        $inventory->add(new SpeciesId('gengar'), new IvSpread(1, 15, 14), League::Great, $this->now());
        $inventory->add(new SpeciesId('gengar'), new IvSpread(1, 15, 14), League::Ultra, $this->now());

        $removed = $inventory->remove(2);
        $added = $inventory->add(new SpeciesId('gastly'), new IvSpread(0, 15, 15), League::Great, $this->now());

        self::assertSame(2, $removed->id);
        self::assertSame(3, $added->id);
    }

    public function testRemovingAnUnknownNumberSaysSo(): void
    {
        $this->expectException(EntryNotFound::class);

        new Inventory()->remove(7);
    }

    public function testTheNextNumberFollowsTheEntriesItIsRestoredWith(): void
    {
        $inventory = new Inventory();
        $inventory->add(new SpeciesId('gengar'), new IvSpread(1, 15, 14), League::Great, $this->now());

        self::assertSame(5, new Inventory($inventory->entries(), 5)->nextId());
        self::assertSame(2, new Inventory($inventory->entries(), 1)->nextId(), 'never below the highest id + 1');
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-11-04 12:00:00');
    }
}
