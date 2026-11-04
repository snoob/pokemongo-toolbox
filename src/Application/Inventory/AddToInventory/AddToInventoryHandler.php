<?php

declare(strict_types=1);

namespace App\Application\Inventory\AddToInventory;

use App\Application\Inventory\InventoryRanker;
use App\Application\Inventory\KeptPokemon;
use App\Application\Inventory\Standing;
use App\Domain\Inventory\Exception\DuplicateEntry;
use App\Domain\Inventory\Model\Inventory;
use App\Domain\Inventory\Model\InventoryEntry;
use App\Domain\Inventory\Port\InventoryRepository;
use App\Domain\Pokemon\Model\IvFloor;
use App\Domain\Pokemon\Model\IvSpread;
use App\Domain\Pokemon\Model\Species;
use App\Domain\Pokemon\Port\SpeciesCatalog;
use App\Domain\Pokemon\Service\SpeciesResolver;
use App\Domain\Pvp\Exception\IvMismatch;
use App\Domain\Pvp\Model\IvDeduction;
use App\Domain\Pvp\Model\PokemonLevel;
use App\Domain\Pvp\Service\IvDeducer;
use Psr\Clock\ClockInterface;

final readonly class AddToInventoryHandler
{
    public function __construct(
        private SpeciesResolver $resolver,
        private SpeciesCatalog $catalog,
        private IvDeducer $deducer,
        private InventoryRepository $repository,
        private InventoryRanker $ranker,
        private ClockInterface $clock,
    ) {}

    /**
     * @throws IvMismatch     when the CP does not settle the IVs
     * @throws DuplicateEntry when that Pokémon is already kept for one of the leagues
     */
    public function __invoke(AddToInventoryCommand $command): AddToInventoryResult
    {
        $match = $command->shadow
            ? $this->resolver->resolveShadow($command->identifier)
            : $this->resolver->resolve($command->identifier);
        $species = $match->species;

        [$iv, $deduction] = $this->read($species, $command);

        $inventory = $this->repository->load();
        $entries = $inventory->add($species->id, $iv, $command->leagues, $this->clock->now());
        $this->repository->save($inventory);

        $added = array_map(fn(InventoryEntry $entry): AddedEntry => $this->compare(
            $entry,
            $species,
            $inventory,
        ), $entries);

        return new AddToInventoryResult(
            $species,
            $iv,
            $added,
            $match->guessed ? $command->identifier : null,
            $deduction,
        );
    }

    /**
     * @return array{IvSpread, ?IvDeduction}
     */
    private function read(Species $species, AddToInventoryCommand $command): array
    {
        $spread = $command->iv->toSpread();

        if (null === $command->cp) {
            // The command refuses an incomplete pattern without a CP.
            return [$spread ?? throw new \LogicException('IVs left unknown without a CP.'), null];
        }

        $deduction = $this->deducer->deduce(
            $species->baseStats,
            $command->iv,
            $command->cp,
            IvFloor::wild(),
            PokemonLevel::regularCap(),
        );

        return [$deduction->iv, $deduction];
    }

    /**
     * The new entry against the others of its league that can become the same form: the
     * best of them is only worth naming when it beats the newcomer.
     */
    private function compare(InventoryEntry $added, Species $species, Inventory $inventory): AddedEntry
    {
        $standing = $this->ranker->standing($species, $added->iv, $added->league);

        if (null === $standing->spread) {
            return new AddedEntry($added, $standing);
        }

        $better = null;
        $toBeat = $standing->spread->position;

        foreach ($inventory->entries($added->league) as $entry) {
            $kept = $this->keptAs($entry, $added, $standing);

            if (null === $kept || $kept->spread->position >= $toBeat) {
                continue;
            }

            $better = $kept;
            $toBeat = $kept->spread->position;
        }

        return new AddedEntry($added, $standing, $better);
    }

    private function keptAs(InventoryEntry $entry, InventoryEntry $added, Standing $standing): ?KeptPokemon
    {
        if ($entry->id === $added->id) {
            return null;
        }

        $species = $this->catalog->find($entry->species);

        if (null === $species || !$this->ranker->canBecome($species, $standing->form)) {
            return null;
        }

        $spread = $this->ranker->spreadAs($standing->form, $entry->iv, $entry->league);

        return null === $spread ? null : new KeptPokemon($entry, $species, $spread);
    }
}
