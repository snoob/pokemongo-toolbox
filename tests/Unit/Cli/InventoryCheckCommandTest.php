<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cli;

use App\Application\Inventory\CheckInventory\CheckInventoryHandler;
use App\Application\Inventory\InventoryRanker;
use App\Domain\Inventory\Model\Account;
use App\Domain\Inventory\Model\Inventory;
use App\Domain\Pokemon\Model\BaseStats;
use App\Domain\Pokemon\Model\DexNumber;
use App\Domain\Pokemon\Model\IvSpread;
use App\Domain\Pokemon\Model\Species;
use App\Domain\Pokemon\Model\SpeciesId;
use App\Domain\Pokemon\Service\EvolutionTree;
use App\Domain\Pvp\Model\League;
use App\Domain\Pvp\Model\SpeciesRank;
use App\Domain\Pvp\Service\CpCalculator;
use App\Domain\Pvp\Service\IvRankCalculator;
use App\Infrastructure\Catalog\NameNormalizer;
use App\Infrastructure\Naming\SpeciesNameResolver;
use App\Infrastructure\Settings\SettingsFile;
use App\Tests\Fake\FixtureTranslator;
use App\Tests\Fake\InMemoryInventoryRepository;
use App\Tests\Fake\InMemoryPvpRankingCatalog;
use App\Tests\Fake\InMemorySpeciesCatalog;
use App\Tests\Fake\RealCpMultiplierTable;
use App\UI\Cli\Command\InventoryCheckCommand;
use App\UI\Cli\Presenter\InventoryCheckTextPresenter;
use App\UI\Cli\Presenter\InventoryJsonPresenter;
use App\UI\Cli\Presenter\InventoryOrder;
use App\UI\Cli\Presenter\StandingFormatter;
use App\UI\Cli\Settings\AccountHint;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class InventoryCheckCommandTest extends TestCase
{
    private InMemoryInventoryRepository $repository;

    #[\Override]
    protected function setUp(): void
    {
        $this->repository = new InMemoryInventoryRepository();
    }

    public function testAnEmptyInventoryIsSaidAndIsNoError(): void
    {
        $tester = $this->check(['--floor' => 'best']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Nothing kept in these leagues', $tester->getDisplay());
    }

    public function testNoFloorIsAnError(): void
    {
        self::assertSame(Command::INVALID, $this->check([])->getStatusCode());
    }

    public function testThoseATradeCanImproveAreTabledTheOthersNamed(): void
    {
        $inventory = new Inventory();
        $at = new \DateTimeImmutable('2026-11-10');
        // 15/0/0 is a poor Gengar; in the Master League no trade beats a 15/15/15.
        $inventory->add(new SpeciesId('gengar'), new IvSpread(15, 0, 0), League::Great, $at);
        $inventory->add(new SpeciesId('gengar'), new IvSpread(15, 15, 15), League::Master, $at);
        $this->repository->save(Account::default(), $inventory);

        $display = $this->check(['--floor' => 'best'])->getDisplay(true);

        self::assertStringContainsString('Great League — a trade can do better', $display);
        self::assertMatchesRegularExpression('/Gengar\s+15\/0\/0/', $display);
        self::assertStringContainsString('No trade can beat — Master League: Gengar 15/15/15', $display);
    }

    public function testTheJsonCarriesTheOdds(): void
    {
        $inventory = new Inventory();
        $inventory->add(new SpeciesId('gengar'), new IvSpread(15, 0, 0), League::Great, new \DateTimeImmutable());
        $this->repository->save(Account::default(), $inventory);

        /** @var list<array<string, mixed>> $json */
        $json = json_decode(
            $this->check(['--floor' => 'best', '--format' => 'json'])->getDisplay(),
            true,
            flags: \JSON_THROW_ON_ERROR,
        );
        $entry = $json[0] ?? self::fail('one entry');

        self::assertSame(11 ** 3, $entry['spreadsWithinFloor'] ?? null);
        self::assertSame(1282, $entry['beatenWithinFloor'] ?? null);
        self::assertSame(['iv' => '5/14/14', 'position' => 27], $entry['bestWithinFloor'] ?? null);
    }

    /**
     * @param array<string, string> $options
     */
    private function check(array $options): CommandTester
    {
        $stats = new BaseStats(261, 149, 155);
        $catalog = new InMemorySpeciesCatalog([new Species(new SpeciesId('gengar'), new DexNumber(94), $stats, true)], [
            'gengar' => ['Gengar', 'Ectoplasma'],
        ]);
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
        $settings = sys_get_temp_dir() . '/pogo-check-' . bin2hex(random_bytes(6)) . '.json';

        $tester = new CommandTester(
            new InventoryCheckCommand(
                new CheckInventoryHandler($this->repository, $catalog, $ranker),
                new InventoryOrder($names, new NameNormalizer()),
                new InventoryCheckTextPresenter($names, $standings),
                new InventoryJsonPresenter($names),
                new AccountHint(new SettingsFile($settings)),
            ),
        );
        $tester->execute($options);

        return $tester;
    }
}
