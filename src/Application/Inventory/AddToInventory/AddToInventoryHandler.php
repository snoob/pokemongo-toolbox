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
use App\Domain\Pokemon\Model\IvPattern;
use App\Domain\Pokemon\Model\IvSpread;
use App\Domain\Pokemon\Model\Species;
use App\Domain\Pokemon\Port\SpeciesCatalog;
use App\Domain\Pokemon\Service\SpeciesResolver;
use App\Domain\Pvp\Exception\EvolvesOverCap;
use App\Domain\Pvp\Exception\IvMismatch;
use App\Domain\Pvp\Exception\RankOutOfRange;
use App\Domain\Pvp\Model\Cp;
use App\Domain\Pvp\Model\League;
use App\Domain\Pvp\Model\PokemonLevel;
use App\Domain\Pvp\Service\CpCalculator;
use App\Domain\Pvp\Service\IvDeducer;
use Psr\Clock\ClockInterface;

final readonly class AddToInventoryHandler
{
    public function __construct(
        private SpeciesResolver $resolver,
        private SpeciesCatalog $catalog,
        private InventoryRepository $repository,
        private InventoryRanker $ranker,
        private ClockInterface $clock,
        private IvDeducer $deducer,
        private CpCalculator $cp,
    ) {}

    /**
     * Nothing is written unless every check passes.
     *
     * @throws DuplicateEntry when that Pokémon is already kept for the league
     * @throws RankOutOfRange when the rank names no spread of the form the league ranks best
     * @throws IvMismatch when no level gives that CP with those IVs
     * @throws EvolvesOverCap when the Pokémon is powered up past what its league allows
     */
    public function __invoke(AddToInventoryCommand $command): AddToInventoryResult
    {
        $match = $command->shadow
            ? $this->resolver->resolveShadow($command->identifier)
            : $this->resolver->resolve($command->identifier);
        $species = $match->species;
        $league = $command->league;

        $iv = $command->ivs instanceof IvSpread ? $command->ivs : $this->spreadRanked($species, $league, $command->ivs);
        $standing = $this->ranker->standing($species, $iv, $league);

        if (null !== $command->cp) {
            $this->checkCp($species, $iv, $command->cp, $standing->form, $league);
        }

        $inventory = $this->repository->load($command->account);
        $entry = $inventory->add($species->id, $iv, $league, $this->clock->now());
        $this->repository->save($command->account, $inventory);

        return new AddToInventoryResult(
            $species,
            $this->compare($entry, $standing, $inventory),
            $command->account,
            $match->guessed ? $command->identifier : null,
        );
    }

    /**
     * The rank is read as `list` shows it: as the form the league ranks best.
     *
     * @throws RankOutOfRange
     */
    private function spreadRanked(Species $inHand, League $league, int $rank): IvSpread
    {
        [$form] = $this->ranker->bestFormIn($inHand, $league);
        $ranking = $this->ranker->rankingAs($form, $league);
        $spread = $ranking?->at($rank);

        if (null === $spread) {
            throw new RankOutOfRange($form, $league, $rank, $ranking?->best()->total);
        }

        return $spread->iv;
    }

    /**
     * Evolving keeps the level, so the CP in hand fixes the level the evolved form will
     * fight at. Of two levels sharing a low CP, the lower one: refuse only what cannot fit.
     *
     * @throws IvMismatch
     * @throws EvolvesOverCap
     */
    private function checkCp(Species $inHand, IvSpread $iv, Cp $cp, Species $form, League $league): void
    {
        $level = $this->deducer->deduce(
            $inHand->baseStats,
            IvPattern::fromString((string) $iv),
            $cp,
            PokemonLevel::bestBuddyCap(),
        )->levels[0];
        $cap = $league->cpCap();
        $formCp = $this->cp->cpAt($form->baseStats, $iv, $level);

        if (null !== $cap && $formCp->exceeds($cap)) {
            throw new EvolvesOverCap($cp, $level, $form, $formCp, $league);
        }
    }

    /**
     * The new entry against the others of its league that can become the same form: the
     * best of them is only worth naming when it beats the newcomer.
     */
    private function compare(InventoryEntry $added, Standing $standing, Inventory $inventory): AddedEntry
    {
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
