<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cli;

use App\Application\Inventory\InventoryRanker;
use App\Application\Inventory\ListInventory\ListInventoryHandler;
use App\Application\Inventory\RemoveFromInventory\RemoveFromInventoryHandler;
use App\Domain\Inventory\Model\Account;
use App\Domain\Inventory\Model\Inventory;
use App\Domain\Inventory\Model\InventoryEntry;
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
use App\Infrastructure\Catalog\NameNormalizer;
use App\Infrastructure\Naming\SpeciesNameResolver;
use App\Tests\Fake\FixtureTranslator;
use App\Tests\Fake\InMemoryInventoryRepository;
use App\Tests\Fake\InMemoryPvpRankingCatalog;
use App\Tests\Fake\InMemorySpeciesCatalog;
use App\Tests\Fake\RealCpMultiplierTable;
use App\UI\Cli\Command\InventoryRemoveCommand;
use App\UI\Cli\Input\PokemonCompletion;
use App\UI\Cli\Presenter\InventoryAddTextPresenter;
use App\UI\Cli\Presenter\InventoryOrder;
use App\UI\Cli\Presenter\KeptPokemonLabel;
use App\UI\Cli\Presenter\StandingFormatter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class InventoryRemoveCommandTest extends TestCase
{
    private InMemoryInventoryRepository $repository;

    #[\Override]
    protected function setUp(): void
    {
        $this->repository = new InMemoryInventoryRepository();
        $inventory = new Inventory();
        $at = new \DateTimeImmutable('2026-11-10');
        $inventory->add(new SpeciesId('gengar'), new IvSpread(1, 15, 14), League::Great, $at);
        $inventory->add(new SpeciesId('gengar'), new IvSpread(0, 15, 15), League::Great, $at);
        $inventory->add(new SpeciesId('gengar_shadow'), new IvSpread(1, 15, 14), League::Ultra, $at);
        $this->repository->save(Account::default(), $inventory);
    }

    public function testAllThreeArgumentsNameOneKeptPokemon(): void
    {
        $tester = $this->remove(['pokemon' => 'gengar', 'league' => 'great', 'ivs|rank' => '0/15/15']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Removed Gengar 0/15/15, Great League', $tester->getDisplay());
        self::assertSame([1, 3], $this->kept());
    }

    public function testSeveralMatchesOutsideATerminalAreListedAndNothingIsRemoved(): void
    {
        $tester = $this->remove(['pokemon' => 'ectoplasma'], interactive: false);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('3 kept Pokémon match', $tester->getDisplay());
        self::assertSame([1, 2, 3], $this->kept());
    }

    public function testSeveralMatchesArePickedFrom(): void
    {
        // Listed best first: 1/15/14 (#131) then 0/15/15 (#216); the second is picked.
        $tester = $this->remove(['pokemon' => 'gengar', 'league' => 'great'], inputs: ['1']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Removed Gengar 0/15/15, Great League', $tester->getDisplay());
        self::assertSame([1, 3], $this->kept());
    }

    public function testNothingMatchingIsSaid(): void
    {
        $tester = $this->remove(['pokemon' => 'gengar', 'league' => 'master']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('Nothing kept matches', $tester->getDisplay());
    }

    /**
     * @return list<int>
     */
    private function kept(): array
    {
        return array_map(
            static fn(InventoryEntry $e): int => $e->id,
            $this->repository->load(Account::default())->entries(),
        );
    }

    /**
     * @param array<string, string> $arguments
     * @param list<string> $inputs
     */
    private function remove(array $arguments, bool $interactive = true, array $inputs = []): CommandTester
    {
        $stats = new BaseStats(261, 149, 155);
        $catalog = new InMemorySpeciesCatalog([
            new Species(new SpeciesId('gengar'), new DexNumber(94), $stats, true),
            new Species(new SpeciesId('gengar_shadow'), new DexNumber(94), $stats, true, forms: ['shadow']),
        ], ['gengar' => ['Gengar', 'Ectoplasma']]);
        $multipliers = new RealCpMultiplierTable();
        $ranker = new InventoryRanker(
            new EvolutionTree($catalog),
            new InMemoryPvpRankingCatalog([
                League::Great->value => ['gengar' => new SpeciesRank(League::Great, 634, 1154, 71.5)],
            ]),
            new IvRankCalculator(new CpCalculator($multipliers), $multipliers),
        );
        $names = new SpeciesNameResolver(FixtureTranslator::create('en'));
        $standings = new StandingFormatter($names);

        $tester = new CommandTester(
            new InventoryRemoveCommand(
                new ListInventoryHandler($this->repository, $catalog, $ranker, new SpeciesResolver($catalog)),
                new RemoveFromInventoryHandler($this->repository, $catalog),
                new InventoryOrder($names, new NameNormalizer()),
                $names,
                new KeptPokemonLabel($names, $standings),
                new InventoryAddTextPresenter($names, $standings),
                new PokemonCompletion($catalog),
            ),
        );
        $tester->setInputs($inputs);
        $tester->execute($arguments, ['interactive' => $interactive]);

        return $tester;
    }
}
