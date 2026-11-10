<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Inventory;

use App\Application\Inventory\AddToInventory\AddToInventoryCommand;
use App\Application\Inventory\AddToInventory\AddToInventoryHandler;
use App\Application\Inventory\AddToInventory\AddToInventoryResult;
use App\Application\Inventory\CheckInventory\CheckInventoryHandler;
use App\Application\Inventory\CheckInventory\CheckInventoryQuery;
use App\Application\Inventory\CheckInventory\CheckInventoryResult;
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
use App\Domain\Pokemon\Model\IvFloor;
use App\Domain\Pokemon\Model\IvSpread;
use App\Domain\Pokemon\Model\Species;
use App\Domain\Pokemon\Model\SpeciesId;
use App\Domain\Pokemon\Service\EvolutionTree;
use App\Domain\Pokemon\Service\SpeciesResolver;
use App\Domain\Pvp\Exception\EvolvesOverCap;
use App\Domain\Pvp\Exception\IvMismatch;
use App\Domain\Pvp\Exception\RankOutOfRange;
use App\Domain\Pvp\Model\Cp;
use App\Domain\Pvp\Model\League;
use App\Domain\Pvp\Model\SpeciesRank;
use App\Domain\Pvp\Service\CpCalculator;
use App\Domain\Pvp\Service\IvDeducer;
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
    private RealCpMultiplierTable $multipliers;
    private CpCalculator $cp;

    #[\Override]
    protected function setUp(): void
    {
        // Real base stats, so the IV ranks are the game's.
        $this->catalog = new InMemorySpeciesCatalog(
            [
                $this->species('dratini', 147, new BaseStats(119, 91, 121), evolutions: ['dragonair']),
                $this->species('dragonair', 148, new BaseStats(163, 135, 156), evolutions: ['dragonite']),
                $this->species('dragonite', 149, new BaseStats(263, 198, 209)),
                $this->species('gengar', 94, new BaseStats(261, 149, 155)),
                $this->species('gengar_shadow', 94, new BaseStats(261, 149, 155), ['shadow']),
                $this->species('gengar_mega', 94, new BaseStats(349, 199, 155), ['mega']),
                // Made up: over 1500 CP even at level 1.
                $this->species('colossus', 999, new BaseStats(3000, 3000, 3000)),
            ],
            [
                'colossus' => ['Colossus', 'Colosse'],
                'dratini' => ['Dratini', 'Minidraco'],
                'dragonair' => ['Dragonair', 'Draco'],
                'dragonite' => ['Dragonite', 'Dracolosse'],
                'gengar' => ['Gengar', 'Ectoplasma'],
            ],
        );
        $this->repository = new InMemoryInventoryRepository();

        $multipliers = new RealCpMultiplierTable();
        $this->multipliers = $multipliers;
        $this->cp = new CpCalculator($multipliers);
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
            new IvRankCalculator($this->cp, $multipliers),
        );
    }

    public function testAPokemonIsRankedAsTheFormItsLeagueRanksBestNotItsLastEvolution(): void
    {
        $great = $this->add('Minidraco', '0/15/15', League::Great);
        $ultra = $this->add('Minidraco', '0/15/15', League::Ultra);

        self::assertSame('dragonair', $great->added->standing->form->id->value);
        self::assertSame(82, $great->added->standing->speciesRank?->position);
        self::assertSame('dragonite', $ultra->added->standing->form->id->value);
        self::assertSame(2, $ultra->added->entry->id, 'the same Pokémon, kept for a second league');
    }

    public function testARankIsReadAsTheFormTheLeagueRanksBest(): void
    {
        // 0/15/15 is the 6th best Dragonair in the Great League.
        $result = $this->addRanked('Minidraco', 6, League::Great);

        self::assertSame('0/15/15', (string) $result->added->entry->iv, 'the IVs are kept, not the rank');
        self::assertSame(6, $result->added->standing->spread?->position);
    }

    public function testARankOfAFormThatCannotEnterTheLeagueIsRefused(): void
    {
        try {
            $this->addRanked('colossus', 1, League::Great);
            self::fail('no spread fits under 1500 CP, so no rank names one');
        } catch (RankOutOfRange $e) {
            self::assertNull($e->total);
            self::assertSame(0, $this->repository->saves);
        }
    }

    public function testACpThatLetsTheEvolutionFitIsAccepted(): void
    {
        // A 0/15/15 Dratini at 815 CP is level 34: a 1490 CP Dragonair.
        $result = $this->add('Minidraco', '0/15/15', League::Great, cp: 815);

        self::assertSame(1, $result->added->entry->id);
    }

    public function testAPokemonPoweredUpPastWhatItsEvolutionAllowsIsRefusedAndNothingSaved(): void
    {
        try {
            // At 822 CP it is level 34.5: the Dragonair would show 1502 CP.
            $this->add('Minidraco', '0/15/15', League::Great, cp: 822);
            self::fail('a Dragonair over 1500 CP cannot be kept for the Great League');
        } catch (EvolvesOverCap $e) {
            self::assertSame('dragonair', $e->form->id->value);
            self::assertSame(1502, $e->formCp->value);
            self::assertSame('34.5', (string) $e->level);
            self::assertSame(0, $this->repository->saves);
        }
    }

    public function testACpNoLevelGivesIsRefused(): void
    {
        $this->expectException(IvMismatch::class);

        // 815 is level 34 for 0/15/15; 15/15/15 never shows it.
        $this->add('Minidraco', '15/15/15', League::Great, cp: 815);
    }

    public function testAnUncappedLeagueOnlyChecksTheCp(): void
    {
        $result = $this->add('Minidraco', '0/15/15', League::Master, cp: 822);

        self::assertSame(1, $result->added->entry->id);
    }

    public function testAMegaIsOnlyConsideredInAMegaEdition(): void
    {
        self::assertSame(
            'gengar',
            $this
                ->add('gengar', '1/15/14', League::Great)
                ->added
                ->standing
                ->form
                ->id
                ->value,
        );
        self::assertSame(
            'gengar_mega',
            $this
                ->add('gengar', '1/15/14', League::MegaGreat)
                ->added
                ->standing
                ->form
                ->id
                ->value,
        );
    }

    public function testABetterPokemonOfTheFamilyAlreadyKeptIsNamed(): void
    {
        $this->add('Minidraco', '0/15/15', League::Great);

        $better = $this->add('Draco', '2/14/15', League::Great)->added->better;

        self::assertNotNull($better, 'a Dratini kept for the Great League counts as the Dragonair it becomes');
        self::assertSame(1, $better->entry->id);
        self::assertSame('dratini', $better->species->id->value);
    }

    public function testAWorsePokemonAlreadyKeptIsNotNamed(): void
    {
        $this->add('Draco', '2/14/15', League::Great);

        self::assertNull($this->add('Minidraco', '0/15/15', League::Great)->added->better);
    }

    public function testAnotherLeagueOrAnotherFamilyIsNoComparison(): void
    {
        $this->add('Minidraco', '0/15/15', League::Ultra);
        $this->add('Gengar', '0/13/13', League::Great);

        self::assertNull($this->add('Draco', '2/14/15', League::Great)->added->better);
    }

    public function testALaterFormCannotStandForAnEarlierOne(): void
    {
        // A Dragonite kept for the Great League cannot become the Dragonair the league
        // ranks best, so it does not beat a Dratini there.
        $this->add('Dracolosse', '0/15/15', League::Great);

        self::assertNull($this->add('Minidraco', '15/0/0', League::Great)->added->better);
    }

    public function testAShadowIsKeptAsItsShadowForm(): void
    {
        $result = $this->addHandler()(
            new AddToInventoryCommand('gengar', IvSpread::fromString('1/15/14'), League::Great, shadow: true),
        );

        self::assertSame('gengar_shadow', $result->added->entry->species->value);
    }

    public function testEachAccountKeepsItsOwnInventoryAndNumbering(): void
    {
        $main = new Account('snoob2555');
        $this->add('gengar', '1/15/14', League::Great);

        $result = $this->addHandler()(
            new AddToInventoryCommand('gengar', IvSpread::fromString('1/15/14'), League::Great, account: $main),
        );

        self::assertSame(1, $result->added->entry->id, 'not a duplicate: another account');
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
        $this->add('Minidraco', '0/15/15', League::Great);
        $this->add('Minidraco', '0/15/15', League::Ultra);

        self::assertCount(1, $this->list(League::Ultra)->lines);
    }

    public function testAPokemonIsFoundByWhatInventoryAddTakes(): void
    {
        $this->add('Minidraco', '0/15/15', League::Great);
        $this->add('Minidraco', '0/15/15', League::Ultra);
        $this->add('Minidraco', '1/14/15', League::Great);
        $this->add('Draco', '0/15/15', League::Great);

        self::assertSame([1, 3, 2], self::ids($this->find(new ListInventoryQuery(pokemon: 'minidraco'))));
        self::assertSame([1, 3], self::ids($this->find(new ListInventoryQuery(League::Great, pokemon: 'minidraco'))));
        self::assertSame(
            [1],
            self::ids($this->find(
                new ListInventoryQuery(League::Great, pokemon: 'minidraco', ivs: IvSpread::fromString('0/15/15')),
            )),
        );
    }

    public function testARankFindsTheSpreadAsInventoryAddReadsIt(): void
    {
        $this->add('Minidraco', '0/15/15', League::Great);
        $this->add('Minidraco', '1/14/15', League::Great);

        // 0/15/15 is the 6th best Dragonair in the Great League.
        self::assertSame(
            [1],
            self::ids($this->find(new ListInventoryQuery(League::Great, pokemon: 'minidraco', ivs: 6))),
        );
    }

    public function testANameAloneFindsItsShadowTooTheFlagOnlyTheShadow(): void
    {
        $this->add('gengar', '1/15/14', League::Great);
        $this->addHandler()(
            new AddToInventoryCommand('gengar', IvSpread::fromString('2/15/14'), League::Great, shadow: true),
        );

        self::assertCount(2, $this->find(new ListInventoryQuery(pokemon: 'ectoplasma'))->lines);
        self::assertSame([2], self::ids($this->find(new ListInventoryQuery(pokemon: 'ectoplasma', shadow: true))));
    }

    public function testACheckWeighsEachPokemonAsTheFormItsLeagueRanksBest(): void
    {
        $this->add('Minidraco', '0/15/15', League::Great);
        $this->add('Minidraco', '0/15/15', League::Ultra);
        $floor = IvFloor::fromString('best');

        $checked = $this->check(new CheckInventoryQuery($floor, [League::Great]))->checked;
        $dragonair = $this->catalog->find(new SpeciesId('dragonair')) ?? self::fail('in the catalog');
        $expected = $this->ranker->rankingAs($dragonair, League::Great)?->tradeOdds(new IvSpread(0, 15, 15), $floor);

        $odds = ($checked[0] ?? self::fail('the Great League entry is checked'))->odds;

        self::assertCount(1, $checked, 'only the leagues asked');
        self::assertNotNull($expected);
        self::assertSame($expected->beatenBy, $odds?->beatenBy, 'weighed as a Dragonair');
        self::assertSame(11 ** 3, $odds->total);
    }

    public function testACheckWithoutATradeFloorIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->check(new CheckInventoryQuery(IvFloor::wild()));
    }

    public function testACheckCannotWeighAFormTheGameDataNoLongerKnows(): void
    {
        $inventory = new Inventory();
        $inventory->add(new SpeciesId('missingno'), new IvSpread(1, 2, 3), League::Great, new \DateTimeImmutable());
        $this->repository->save(Account::default(), $inventory);

        $checked = $this->check(new CheckInventoryQuery(IvFloor::fromString('best')))->checked;

        self::assertNull(($checked[0] ?? self::fail('the entry is checked'))->odds);
    }

    public function testAFormTheGameDataNoLongerKnowsIsStillListed(): void
    {
        $inventory = new Inventory();
        $inventory->add(new SpeciesId('missingno'), new IvSpread(1, 2, 3), League::Great, new \DateTimeImmutable());
        $this->repository->save(Account::default(), $inventory);

        $line = $this->list()->lines[0] ?? self::fail('the entry must be listed');

        self::assertNull($line->species);
        self::assertNull($line->standing);
    }

    public function testRemovingAnEntrySavesTheRest(): void
    {
        $this->add('Minidraco', '0/15/15', League::Great);
        $this->add('Minidraco', '0/15/15', League::Ultra);

        $result = (new RemoveFromInventoryHandler($this->repository, $this->catalog))(
            new RemoveFromInventoryCommand(1),
        );

        self::assertSame('dratini', $result->species?->id->value);
        self::assertSame([2], self::ids($this->list()));
    }

    private function add(string $identifier, string $ivs, League $league, ?int $cp = null): AddToInventoryResult
    {
        return $this->addHandler()(
            new AddToInventoryCommand(
                $identifier,
                IvSpread::fromString($ivs),
                $league,
                null === $cp ? null : new Cp($cp),
            ),
        );
    }

    /**
     * @param positive-int $rank
     */
    private function addRanked(string $identifier, int $rank, League $league): AddToInventoryResult
    {
        return $this->addHandler()(new AddToInventoryCommand($identifier, $rank, $league));
    }

    private function addHandler(): AddToInventoryHandler
    {
        return new AddToInventoryHandler(
            new SpeciesResolver($this->catalog),
            $this->catalog,
            $this->repository,
            $this->ranker,
            new MockClock('2026-11-04 12:00:00'),
            new IvDeducer($this->cp, $this->multipliers),
            $this->cp,
        );
    }

    private function list(?League $league = null): ListInventoryResult
    {
        return $this->find(new ListInventoryQuery($league));
    }

    private function check(CheckInventoryQuery $query): CheckInventoryResult
    {
        return (new CheckInventoryHandler($this->repository, $this->catalog, $this->ranker))($query);
    }

    private function find(ListInventoryQuery $query): ListInventoryResult
    {
        return (new ListInventoryHandler(
            $this->repository,
            $this->catalog,
            $this->ranker,
            new SpeciesResolver($this->catalog),
        ))($query);
    }

    /**
     * @return list<int>
     */
    private static function ids(ListInventoryResult $result): array
    {
        return array_map(static fn(InventoryLine $l): int => $l->entry->id, $result->lines);
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
