<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Inventory;

use App\Application\Inventory\AddToInventory\AddToInventoryCommand;
use App\Application\Inventory\AddToInventory\AddToInventoryHandler;
use App\Application\Inventory\AddToInventory\AddToInventoryResult;
use App\Application\Inventory\InventoryRanker;
use App\Application\Inventory\ListInventory\InventoryLine;
use App\Application\Inventory\ListInventory\ListInventoryHandler;
use App\Application\Inventory\ListInventory\ListInventoryQuery;
use App\Application\Inventory\ListInventory\ListInventoryResult;
use App\Application\Inventory\RemoveFromInventory\RemoveFromInventoryCommand;
use App\Application\Inventory\RemoveFromInventory\RemoveFromInventoryHandler;
use App\Domain\Inventory\Exception\DuplicateEntry;
use App\Domain\Inventory\Model\Account;
use App\Domain\Inventory\Model\Inventory;
use App\Domain\Pokemon\Model\BaseStats;
use App\Domain\Pokemon\Model\DexNumber;
use App\Domain\Pokemon\Model\IvSpread;
use App\Domain\Pokemon\Model\Species;
use App\Domain\Pokemon\Model\SpeciesId;
use App\Domain\Pokemon\Service\EvolutionTree;
use App\Domain\Pokemon\Service\SpeciesResolver;
use App\Domain\Pvp\Model\League;
use App\Domain\Pvp\Model\SpeciesRank;
use App\Domain\Pvp\Service\CpCalculator;
use App\Domain\Pvp\Service\IvRankCalculator;
use App\Tests\Fake\InMemoryInventoryRepository;
use App\Tests\Fake\InMemoryPvpRankingCatalog;
use App\Tests\Fake\InMemorySpeciesCatalog;
use App\Tests\Fake\RealCpMultiplierTable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class InventoryHandlersTest extends TestCase
{
    private InMemorySpeciesCatalog $catalog;
    private InMemoryInventoryRepository $repository;
    private InventoryRanker $ranker;

    #[\Override]
    protected function setUp(): void
    {
        // Real base stats, so the IV ranks are the game's.
        $this->catalog = new InMemorySpeciesCatalog([
            $this->species('dratini', 147, new BaseStats(119, 91, 121), evolutions: ['dragonair']),
            $this->species('dragonair', 148, new BaseStats(163, 135, 156), evolutions: ['dragonite']),
            $this->species('dragonite', 149, new BaseStats(263, 198, 209)),
            $this->species('gengar', 94, new BaseStats(261, 149, 155)),
            $this->species('gengar_shadow', 94, new BaseStats(261, 149, 155), ['shadow']),
            $this->species('gengar_mega', 94, new BaseStats(349, 199, 155), ['mega']),
        ], [
            'dratini' => ['Dratini', 'Minidraco'],
            'dragonair' => ['Dragonair', 'Draco'],
            'dragonite' => ['Dragonite', 'Dracolosse'],
            'gengar' => ['Gengar', 'Ectoplasma'],
        ]);
        $this->repository = new InMemoryInventoryRepository();

        $multipliers = new RealCpMultiplierTable();
        $this->ranker = new InventoryRanker(
            new EvolutionTree($this->catalog),
            new InMemoryPvpRankingCatalog([
                League::Great->value => [
                    'dragonair' => new SpeciesRank(League::Great, 82, 1154, 80.0),
                    'dragonite' => new SpeciesRank(League::Great, 700, 1154, 60.0),
                    'gengar' => new SpeciesRank(League::Great, 634, 1154, 71.5),
                ],
                League::Ultra->value => [
                    'dragonair' => new SpeciesRank(League::Ultra, 600, 851, 60.0),
                    'dragonite' => new SpeciesRank(League::Ultra, 354, 851, 75.0),
                ],
                League::MegaGreat->value => [
                    'gengar' => new SpeciesRank(League::MegaGreat, 638, 1212, 71.9),
                    'gengar_mega' => new SpeciesRank(League::MegaGreat, 27, 1212, 90.0),
                ],
            ]),
            new IvRankCalculator(new CpCalculator($multipliers), $multipliers),
        );
    }

    public function testAPokemonIsRankedAsTheFormItsLeagueRanksBestNotItsLastEvolution(): void
    {
        $result = $this->add('Minidraco', '0/15/15', League::Great, League::Ultra);

        self::assertSame('dragonair', $result->added[0]->standing->form->id->value);
        self::assertSame(82, $result->added[0]->standing->speciesRank?->position);
        self::assertSame(
            'dragonite',
            ($result->added[1] ?? self::fail('one entry per league'))->standing->form->id->value,
        );
        self::assertSame(1, $this->repository->saves);
    }

    public function testAMegaIsOnlyConsideredInAMegaEdition(): void
    {
        self::assertSame('gengar', $this->add('gengar', '1/15/14', League::Great)->added[0]->standing->form->id->value);
        self::assertSame(
            'gengar_mega',
            $this->add('gengar', '1/15/14', League::MegaGreat)->added[0]->standing->form->id->value,
        );
    }

    public function testABetterPokemonOfTheFamilyAlreadyKeptIsNamed(): void
    {
        $this->add('Minidraco', '0/15/15', League::Great);

        $better = $this->add('Draco', '2/14/15', League::Great)->added[0]->better;

        self::assertNotNull($better, 'a Dratini kept for the Great League counts as the Dragonair it becomes');
        self::assertSame(1, $better->entry->id);
        self::assertSame('dratini', $better->species->id->value);
    }

    public function testAWorsePokemonAlreadyKeptIsNotNamed(): void
    {
        $this->add('Draco', '2/14/15', League::Great);

        self::assertNull($this->add('Minidraco', '0/15/15', League::Great)->added[0]->better);
    }

    public function testAnotherLeagueOrAnotherFamilyIsNoComparison(): void
    {
        $this->add('Minidraco', '0/15/15', League::Ultra);
        $this->add('Gengar', '0/13/13', League::Great);

        self::assertNull($this->add('Draco', '2/14/15', League::Great)->added[0]->better);
    }

    public function testALaterFormCannotStandForAnEarlierOne(): void
    {
        // A Dragonite kept for the Great League cannot become the Dragonair the league
        // ranks best, so it does not beat a Dratini there.
        $this->add('Dracolosse', '0/15/15', League::Great);

        self::assertNull($this->add('Minidraco', '15/0/0', League::Great)->added[0]->better);
    }

    public function testAShadowIsKeptAsItsShadowForm(): void
    {
        $result = $this->addHandler()(
            new AddToInventoryCommand('gengar', IvSpread::fromString('1/15/14'), [League::Great], shadow: true),
        );

        self::assertSame('gengar_shadow', $result->added[0]->entry->species->value);
    }

    public function testEachAccountKeepsItsOwnInventoryAndNumbering(): void
    {
        $main = new Account('snoob2555');
        $this->add('gengar', '1/15/14', League::Great);

        $result = $this->addHandler()(
            new AddToInventoryCommand('gengar', IvSpread::fromString('1/15/14'), [League::Great], account: $main),
        );

        self::assertSame(1, $result->added[0]->entry->id, 'not a duplicate: another account');
        self::assertSame('snoob2555', $result->account->name);
        self::assertCount(1, $this->list()->lines);

        (new RemoveFromInventoryHandler($this->repository, $this->catalog))(new RemoveFromInventoryCommand(1, $main));

        self::assertCount(1, $this->list()->lines, 'removing from one account leaves the other');
    }

    public function testADuplicateSavesNothing(): void
    {
        $this->add('gengar', '1/15/14', League::Great);

        try {
            $this->add('gengar', '1/15/14', League::Great);
            self::fail('a duplicate must be refused');
        } catch (DuplicateEntry) {
            self::assertSame(1, $this->repository->saves);
        }
    }

    public function testTheListGroupsByLeagueBestFirst(): void
    {
        $this->add('Gengar', '1/15/14', League::Great);
        $this->add('Minidraco', '0/15/15', League::Ultra);
        $this->add('Draco', '2/14/15', League::Great);
        $this->add('Minidraco', '0/15/15', League::Great);

        $lines = $this->list()->lines;

        self::assertSame(
            ['great 4', 'great 3', 'great 1', 'ultra 2'],
            array_map(static fn(InventoryLine $l): string => $l->entry->league->value . ' ' . $l->entry->id, $lines),
            'Dragonair (#82) before Gengar (#634), then the better IVs first',
        );
    }

    public function testTheListCanBeNarrowedToOneLeague(): void
    {
        $this->add('Minidraco', '0/15/15', League::Great, League::Ultra);

        self::assertCount(1, $this->list(League::Ultra)->lines);
    }

    public function testAFormTheGameDataNoLongerKnowsIsStillListed(): void
    {
        $inventory = new Inventory();
        $inventory->add(new SpeciesId('missingno'), new IvSpread(1, 2, 3), [League::Great], new \DateTimeImmutable());
        $this->repository->save(Account::default(), $inventory);

        $line = $this->list()->lines[0] ?? self::fail('the entry must be listed');

        self::assertNull($line->species);
        self::assertNull($line->standing);
    }

    public function testRemovingAnEntrySavesTheRest(): void
    {
        $this->add('Minidraco', '0/15/15', League::Great, League::Ultra);

        $result = (new RemoveFromInventoryHandler($this->repository, $this->catalog))(
            new RemoveFromInventoryCommand(1),
        );

        self::assertSame('dratini', $result->species?->id->value);
        self::assertSame([2], array_map(static fn(InventoryLine $l): int => $l->entry->id, $this->list()->lines));
    }

    private function add(string $identifier, string $ivs, League $league, League ...$more): AddToInventoryResult
    {
        return $this->addHandler()(new AddToInventoryCommand($identifier, IvSpread::fromString($ivs), [
            $league,
            ...array_values($more),
        ]));
    }

    private function addHandler(): AddToInventoryHandler
    {
        return new AddToInventoryHandler(
            new SpeciesResolver($this->catalog),
            $this->catalog,
            $this->repository,
            $this->ranker,
            new MockClock('2026-11-04 12:00:00'),
        );
    }

    private function list(?League $league = null): ListInventoryResult
    {
        return (new ListInventoryHandler($this->repository, $this->catalog, $this->ranker))(
            new ListInventoryQuery($league),
        );
    }

    /**
     * @param list<string> $forms
     * @param list<string> $evolutions
     */
    private function species(string $id, int $dex, BaseStats $stats, array $forms = [], array $evolutions = []): Species
    {
        return new Species(
            new SpeciesId($id),
            new DexNumber($dex),
            $stats,
            shadowEligible: true,
            forms: $forms,
            evolutions: array_map(static fn(string $to): SpeciesId => new SpeciesId($to), $evolutions),
        );
    }
}
