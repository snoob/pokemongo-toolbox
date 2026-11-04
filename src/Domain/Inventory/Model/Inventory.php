<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Model;

use App\Domain\Inventory\Exception\DuplicateEntry;
use App\Domain\Inventory\Exception\EntryNotFound;
use App\Domain\Pokemon\Model\IvSpread;
use App\Domain\Pokemon\Model\SpeciesId;
use App\Domain\Pvp\Model\League;

/**
 * The Pokémon a player keeps, league by league. Ids are never reused: a removed entry's
 * number does not come back to name another Pokémon.
 */
final class Inventory
{
    /**
     * @param list<InventoryEntry> $entries
     */
    public function __construct(
        private array $entries = [],
        private int $nextId = 1,
    ) {
        foreach ($entries as $entry) {
            $this->nextId = max($this->nextId, $entry->id + 1);
        }
    }

    /**
     * One entry per league, all or none: a duplicate in any league adds nothing.
     *
     * @param non-empty-list<League> $leagues
     *
     * @throws DuplicateEntry
     *
     * @return non-empty-list<InventoryEntry>
     */
    public function add(SpeciesId $species, IvSpread $iv, array $leagues, \DateTimeImmutable $at): array
    {
        $leagues = array_values(array_unique($leagues, \SORT_REGULAR));

        foreach ($leagues as $league) {
            foreach ($this->entries as $entry) {
                if ($entry->isSameAs($species, $iv, $league)) {
                    throw DuplicateEntry::of($entry);
                }
            }
        }

        $added = [];

        foreach ($leagues as $league) {
            $entry = new InventoryEntry($this->nextId++, $species, $iv, $league, $at);
            $this->entries[] = $entry;
            $added[] = $entry;
        }

        return $added;
    }

    /**
     * @throws EntryNotFound
     */
    public function remove(int $id): InventoryEntry
    {
        foreach ($this->entries as $index => $entry) {
            if ($id !== $entry->id) {
                continue;
            }

            unset($this->entries[$index]);
            $this->entries = array_values($this->entries);

            return $entry;
        }

        throw EntryNotFound::withId($id);
    }

    /**
     * @return list<InventoryEntry> in the order they were added
     */
    public function entries(?League $league = null): array
    {
        if (null === $league) {
            return $this->entries;
        }

        return array_values(array_filter(
            $this->entries,
            static fn(InventoryEntry $entry): bool => $league === $entry->league,
        ));
    }

    public function nextId(): int
    {
        return $this->nextId;
    }
}
