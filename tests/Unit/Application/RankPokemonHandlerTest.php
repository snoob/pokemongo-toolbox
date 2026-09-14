<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application;

use App\Application\Pvp\RankPokemon\RankPokemonHandler;
use App\Application\Pvp\RankPokemon\RankPokemonQuery;
use App\Domain\Pokemon\Exception\AmbiguousSpecies;
use App\Domain\Pokemon\Exception\SpeciesNotFound;
use App\Domain\Pokemon\Model\BaseStats;
use App\Domain\Pokemon\Model\DexNumber;
use App\Domain\Pokemon\Model\IvSpread;
use App\Domain\Pokemon\Model\Species;
use App\Domain\Pokemon\Model\SpeciesId;
use App\Domain\Pvp\Model\League;
use App\Domain\Pvp\Model\SpeciesRank;
use App\Domain\Pvp\Service\CpCalculator;
use App\Domain\Pvp\Service\IvRankCalculator;
use App\Tests\Fake\InMemoryPvpRankingCatalog;
use App\Tests\Fake\InMemorySpeciesCatalog;
use App\Tests\Fake\RealCpMultiplierTable;
use PHPUnit\Framework\TestCase;

final class RankPokemonHandlerTest extends TestCase
{
    public function testItResolvesAFrenchNameAndReportsEveryRequestedLeague(): void
    {
        $handler = $this->handler();

        $result = $handler(new RankPokemonQuery('Ectoplasma', leagues: [League::Great, League::Ultra]));

        self::assertSame('gengar', $result->species->id->value);
        self::assertCount(2, $result->outcomes);
        self::assertSame(615, $result->outcomeFor(League::Great)?->speciesRank?->position);
        self::assertNull(
            $result->outcomeFor(League::Ultra)?->speciesRank,
            'an unranked league reports nothing rather than a zero',
        );
    }

    public function testItResolvesADexNumberToTheDefaultForm(): void
    {
        $result = $this->handler()(new RankPokemonQuery('94', leagues: [League::Great]));

        self::assertSame('gengar', $result->species->id->value);
    }

    public function testItRanksTheGivenIvsWithinTheSpecies(): void
    {
        $result = $this->handler()(new RankPokemonQuery('gengar', new IvSpread(1, 15, 14), [League::Great]));

        $outcome = $result->outcomeFor(League::Great);

        self::assertNotNull($outcome);
        self::assertSame(130, $outcome->spread?->position);
        self::assertFalse($outcome->ineligible);
    }

    public function testWithoutIvsNoSpreadIsComputed(): void
    {
        $result = $this->handler()(new RankPokemonQuery('gengar', leagues: [League::Great]));

        self::assertNull($result->outcomeFor(League::Great)?->spread);
    }

    public function testAnUnknownNameIsRejected(): void
    {
        $this->expectException(SpeciesNotFound::class);

        $this->handler()(new RankPokemonQuery('Pikachouette'));
    }

    public function testAnAmbiguousIdentifierListsTheCandidatesInsteadOfGuessing(): void
    {
        $catalog = new InMemorySpeciesCatalog([
            $this->species('giratina_altered', 487, ['altered']),
            $this->species('giratina_origin', 487, ['origin']),
        ]);

        try {
            (new RankPokemonHandler($catalog, new InMemoryPvpRankingCatalog(), $this->ivRanker()))(
                new RankPokemonQuery('487'),
            );
            self::fail('An ambiguous identifier must not resolve silently.');
        } catch (AmbiguousSpecies $e) {
            self::assertCount(2, $e->candidates);
            self::assertSame('487', $e->identifier);
        }
    }

    public function testTheShadowFlagSwitchesToTheShadowForm(): void
    {
        $result = $this->handler()(new RankPokemonQuery('gengar', leagues: [League::Great], shadow: true));

        self::assertSame('gengar_shadow', $result->species->id->value);
    }

    public function testTheShadowFlagFallsBackWhenNoShadowFormExists(): void
    {
        $catalog = new InMemorySpeciesCatalog([$this->species('tinkaton', 959)], ['tinkaton' => ['Tinkaton']]);

        $result = (new RankPokemonHandler($catalog, new InMemoryPvpRankingCatalog(), $this->ivRanker()))(
            new RankPokemonQuery('tinkaton', leagues: [League::Great], shadow: true),
        );

        self::assertSame('tinkaton', $result->species->id->value);
    }

    private function handler(): RankPokemonHandler
    {
        $catalog = new InMemorySpeciesCatalog([
            $this->species('gengar', 94),
            $this->species('gengar_shadow', 94, ['shadow']),
            $this->species('gengar_mega', 94, ['mega']),
        ], [
            'gengar' => ['Gengar', 'Ectoplasma'],
            'gengar_shadow' => ['Gengar (Shadow)', 'Ectoplasma (Obscur)'],
            'gengar_mega' => ['Gengar (Mega)', 'Ectoplasma (Méga)'],
        ]);

        $rankings = new InMemoryPvpRankingCatalog([
            League::Great->value => ['gengar' => new SpeciesRank(League::Great, 615, 1146, 73.0)],
        ]);

        return new RankPokemonHandler($catalog, $rankings, $this->ivRanker());
    }

    private function ivRanker(): IvRankCalculator
    {
        $multipliers = new RealCpMultiplierTable();

        return new IvRankCalculator(new CpCalculator($multipliers), $multipliers);
    }

    /**
     * @param list<string> $forms
     */
    private function species(string $id, int $dex, array $forms = []): Species
    {
        return new Species(
            new SpeciesId($id),
            new DexNumber($dex),
            new BaseStats(261, 149, 155),
            shadowEligible: true,
            forms: $forms,
        );
    }
}
