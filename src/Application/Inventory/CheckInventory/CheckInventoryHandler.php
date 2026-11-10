<?php

declare(strict_types=1);

namespace App\Application\Inventory\CheckInventory;

use App\Application\Inventory\InventoryRanker;
use App\Application\Inventory\ListInventory\InventoryLine;
use App\Application\Inventory\Standing;
use App\Domain\Inventory\Model\InventoryEntry;
use App\Domain\Inventory\Port\InventoryRepository;
use App\Domain\Pokemon\Port\SpeciesCatalog;
use App\Domain\Pvp\Model\TradeOdds;

/**
 * Which kept Pokémon a trade can still improve: each is weighed, as the form its league
 * ranks best, against every spread the trade's floor leaves possible.
 */
final readonly class CheckInventoryHandler
{
    public function __construct(
        private InventoryRepository $repository,
        private SpeciesCatalog $catalog,
        private InventoryRanker $ranker,
    ) {}

    /**
     * @throws \InvalidArgumentException when the floor is 0: no trade to weigh
     */
    public function __invoke(CheckInventoryQuery $query): CheckInventoryResult
    {
        if (!$query->floor->restricts()) {
            throw new \InvalidArgumentException(
                'Checking needs the floor of a trade, above 0: --floor=best for a Best Friend one.',
            );
        }

        $entries = array_filter(
            $this->repository->load($query->account)->entries(),
            static fn(InventoryEntry $e): bool => (
                [] === $query->leagues
                || \in_array($e->league, $query->leagues, true)
            ),
        );
        $checked = array_map(fn(InventoryEntry $entry): CheckedPokemon => $this->check(
            $entry,
            $query,
        ), array_values($entries));

        usort(
            $checked,
            static fn(CheckedPokemon $a, CheckedPokemon $b): int => $a->line->rankOrder() <=> $b->line->rankOrder(),
        );

        return new CheckInventoryResult($checked, $query->floor, $query->account);
    }

    private function check(InventoryEntry $entry, CheckInventoryQuery $query): CheckedPokemon
    {
        $species = $this->catalog->find($entry->species);
        $standing = null === $species ? null : $this->ranker->standing($species, $entry->iv, $entry->league);

        return new CheckedPokemon(
            new InventoryLine($entry, $species, $standing),
            $this->odds($entry, $standing, $query),
        );
    }

    private function odds(InventoryEntry $entry, ?Standing $standing, CheckInventoryQuery $query): ?TradeOdds
    {
        if (null === $standing || null === $standing->spread) {
            return null;
        }

        return $this->ranker->rankingAs($standing->form, $entry->league)?->tradeOdds($entry->iv, $query->floor);
    }
}
