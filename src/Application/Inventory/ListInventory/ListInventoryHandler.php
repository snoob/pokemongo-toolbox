<?php

declare(strict_types=1);

namespace App\Application\Inventory\ListInventory;

use App\Application\Inventory\InventoryRanker;
use App\Domain\Inventory\Model\InventoryEntry;
use App\Domain\Inventory\Port\InventoryRepository;
use App\Domain\Pokemon\Exception\AmbiguousSpecies;
use App\Domain\Pokemon\Exception\SpeciesNotFound;
use App\Domain\Pokemon\Model\IvSpread;
use App\Domain\Pokemon\Model\SpeciesId;
use App\Domain\Pokemon\Port\SpeciesCatalog;
use App\Domain\Pokemon\Service\SpeciesResolver;
use App\Domain\Pvp\Exception\RankOutOfRange;

final readonly class ListInventoryHandler
{
    public function __construct(
        private InventoryRepository $repository,
        private SpeciesCatalog $catalog,
        private InventoryRanker $ranker,
        private SpeciesResolver $resolver,
    ) {}

    /**
     * @throws SpeciesNotFound
     * @throws AmbiguousSpecies
     * @throws RankOutOfRange
     */
    public function __invoke(ListInventoryQuery $query): ListInventoryResult
    {
        $entries = $this->repository->load($query->account)->entries($query->league);
        $species = null;
        $guessedFrom = null;

        if (null !== $query->pokemon) {
            $match = $this->resolver->resolve($query->pokemon);
            $species = $match->species;
            $guessedFrom = $match->guessed ? $query->pokemon : null;
            $forms = $query->shadow ? [$species->id->shadow()] : [$species->id, $species->id->shadow()];
            $entries = array_filter($entries, static fn(InventoryEntry $e): bool => self::isOneOf($e->species, $forms));
        }

        $iv = $query->ivs;

        if (\is_int($iv)) {
            if (null === $species || null === $query->league) {
                throw new \InvalidArgumentException('A rank only means something for a Pokémon and a league.');
            }

            $iv = $this->ranker->spreadRanked($species, $query->league, $iv);
        }

        if ($iv instanceof IvSpread) {
            $entries = array_filter($entries, static fn(InventoryEntry $e): bool => $e->iv->equals($iv));
        }

        $lines = array_map($this->line(...), array_values($entries));

        // A stable sort: equal ranks keep the order they were added in. The display breaks
        // those ties by name, which only it knows in the language shown.
        usort($lines, static fn(InventoryLine $a, InventoryLine $b): int => $a->rankOrder() <=> $b->rankOrder());

        return new ListInventoryResult($lines, $query->account, $species, $guessedFrom);
    }

    private function line(InventoryEntry $entry): InventoryLine
    {
        $species = $this->catalog->find($entry->species);

        return new InventoryLine(
            $entry,
            $species,
            null === $species ? null : $this->ranker->standing($species, $entry->iv, $entry->league),
        );
    }

    /**
     * @param list<SpeciesId> $forms
     */
    private static function isOneOf(SpeciesId $id, array $forms): bool
    {
        foreach ($forms as $form) {
            if ($form->equals($id)) {
                return true;
            }
        }

        return false;
    }
}
