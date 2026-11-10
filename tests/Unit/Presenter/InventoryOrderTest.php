<?php

declare(strict_types=1);

namespace App\Tests\Unit\Presenter;

use App\Application\Inventory\ListInventory\InventoryLine;
use App\Application\Inventory\ListInventory\ListInventoryResult;
use App\Application\Inventory\Standing;
use App\Domain\Inventory\Model\Account;
use App\Domain\Inventory\Model\InventoryEntry;
use App\Domain\Pokemon\Model\BaseStats;
use App\Domain\Pokemon\Model\DexNumber;
use App\Domain\Pokemon\Model\IvSpread;
use App\Domain\Pokemon\Model\Species;
use App\Domain\Pokemon\Model\SpeciesId;
use App\Domain\Pvp\Model\Cp;
use App\Domain\Pvp\Model\League;
use App\Domain\Pvp\Model\PokemonLevel;
use App\Domain\Pvp\Model\RankedSpread;
use App\Domain\Pvp\Model\SpeciesRank;
use App\Infrastructure\Catalog\NameNormalizer;
use App\Infrastructure\Naming\SpeciesNameResolver;
use App\Tests\Fake\FixtureTranslator;
use App\UI\Cli\Presenter\InventoryOrder;
use PHPUnit\Framework\TestCase;

final class InventoryOrderTest extends TestCase
{
    public function testTheRankComesFirst(): void
    {
        $lines = $this->sorted('en', $this->line(1, 'haunter', 93, 40, 2), $this->line(2, 'raichu', 26, 10, 2));

        self::assertSame([2, 1], $lines, 'the better species of the league first, whatever its name');
    }

    public function testEqualRanksGoByTheNameShownInTheLanguageShown(): void
    {
        $haunter = $this->line(1, 'haunter', 93, 10, 5);
        $raichu = $this->line(2, 'raichu', 26, 10, 5);

        self::assertSame([1, 2], $this->sorted('en', $raichu, $haunter), 'Haunter before Raichu');
        self::assertSame([2, 1], $this->sorted('fr', $haunter, $raichu), 'Raichu before Spectrum');
    }

    /**
     * @return list<int> the entry numbers, in the order shown
     */
    private function sorted(string $locale, InventoryLine ...$lines): array
    {
        $order = new InventoryOrder(new SpeciesNameResolver(FixtureTranslator::create()), new NameNormalizer());
        $result = $order->sorted(new ListInventoryResult(array_values($lines), Account::default()), $locale);

        return array_map(static fn(InventoryLine $line): int => $line->entry->id, $result->lines);
    }

    private function line(int $id, string $species, int $dex, int $speciesRank, int $ivRank): InventoryLine
    {
        $form = new Species(new SpeciesId($species), new DexNumber($dex), new BaseStats(100, 100, 100), true);
        $iv = new IvSpread(0, 15, 15);

        return new InventoryLine(
            new InventoryEntry($id, $form->id, $iv, League::Great, new \DateTimeImmutable('2026-11-10')),
            $form,
            new Standing(
                $form,
                new SpeciesRank(League::Great, $speciesRank, 1154, 80.0),
                new RankedSpread($iv, $ivRank, 4096, 1.0, 99.0, new Cp(1500), PokemonLevel::regularCap()),
            ),
        );
    }
}
