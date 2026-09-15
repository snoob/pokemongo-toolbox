<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application;

use App\Application\Pvp\RankPokemon\LeagueOutcome;
use App\Application\Pvp\RankPokemon\RankPokemonHandler;
use App\Application\Pvp\RankPokemon\RankPokemonQuery;
use App\Domain\Pokemon\Exception\AmbiguousSpecies;
use App\Domain\Pokemon\Exception\SpeciesNotFound;
use App\Domain\Pokemon\Model\BaseStats;
use App\Domain\Pokemon\Model\DexNumber;
use App\Domain\Pokemon\Model\IvSpread;
use App\Domain\Pokemon\Model\Move;
use App\Domain\Pokemon\Model\MoveId;
use App\Domain\Pokemon\Model\Species;
use App\Domain\Pokemon\Model\SpeciesId;
use App\Domain\Pokemon\Service\SpeciesResolver;
use App\Domain\Pvp\Model\League;
use App\Domain\Pvp\Model\MegaLevel;
use App\Domain\Pvp\Model\Moveset;
use App\Domain\Pvp\Model\PowerUpTarget;
use App\Domain\Pvp\Model\SpeciesRank;
use App\Domain\Pvp\Service\CpCalculator;
use App\Domain\Pvp\Service\ExclusiveMoveFinder;
use App\Domain\Pvp\Service\IvRankCalculator;
use App\Tests\Fake\FixedMegaLevelTable;
use App\Tests\Fake\InMemoryMoveCatalog;
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
        self::assertSame(131, $outcome->spread?->position);
        self::assertFalse($outcome->ineligible);
    }

    public function testWithoutIvsNoSpreadIsComputed(): void
    {
        $result = $this->handler()(new RankPokemonQuery('gengar', leagues: [League::Great]));

        self::assertNull($result->outcomeFor(League::Great)?->spread);
    }

    public function testTheBestSpreadIsReportedEvenWithoutIvs(): void
    {
        $result = $this->handler()(new RankPokemonQuery('gengar', leagues: [League::Great]));

        $outcome = $result->outcomeFor(League::Great);

        self::assertNull($outcome?->spread, 'no IVs given, so nothing to place');
        self::assertSame('0/13/13', (string) $outcome?->best?->iv, 'the spread worth hunting is still answered');
        self::assertSame(1, $outcome?->best?->position);
    }

    public function testAMegaIsRankedInTheMegaEditionsRatherThanTheStandardLeagues(): void
    {
        $result = $this->megaHandler()(new RankPokemonQuery('gengar', megaLevel: MegaLevel::default()));

        self::assertSame(
            ['mega-great', 'mega-ultra', 'mega-master'],
            array_map(static fn(LeagueOutcome $o): string => $o->league->value, $result->outcomes),
            'a mega cannot enter a standard league, so ranking it there would answer nothing',
        );
    }

    public function testANonMegaKeepsTheStandardLeagues(): void
    {
        $result = $this->handler()(new RankPokemonQuery('gengar'));

        self::assertSame(
            ['great', 'ultra', 'master'],
            array_map(static fn(LeagueOutcome $o): string => $o->league->value, $result->outcomes),
        );
    }

    /**
     * A Mega Edition caps the CP of the mega form, so the figure the game shows while
     * powering up — the base form's CP — sits lower and is the one to aim for.
     */
    public function testAMegaReportsTheBaseFormCpToPowerUpTo(): void
    {
        $outcome = $this->megaHandler()(
            new RankPokemonQuery('gengar', new IvSpread(0, 15, 15), megaLevel: MegaLevel::default()),
        )
            ->outcomeFor(League::MegaGreat);

        self::assertNotNull($outcome?->powerUpTo);
        self::assertLessThan(1500, $outcome->powerUpTo->cp->value, 'the base form reads less than the capped mega');
        self::assertNotNull($outcome->spread);
        self::assertLessThanOrEqual(1500, $outcome->spread->cp->value);
    }

    /**
     * Only the last Mega Level carries a CP boost: the mega then fights two Pokémon
     * levels above where it sits, so the form to power up stops that much lower.
     */
    public function testTheLastMegaLevelLowersTheCpToStopAt(): void
    {
        $atThree = $this->megaTarget(MegaLevel::default());
        $atFour = $this->megaTarget(new MegaLevel(4));

        self::assertNotNull($atThree);
        self::assertNotNull($atFour);
        self::assertSame($atThree->level->value - 2.0, $atFour->level->value);
        self::assertLessThan($atThree->cp->value, $atFour->cp->value);
    }

    public function testTheLevelsBelowTheLastChangeNothing(): void
    {
        $first = $this->megaTarget(new MegaLevel(1));
        $third = $this->megaTarget(new MegaLevel(3));

        self::assertSame($first?->cp->value, $third?->cp->value);
    }

    /**
     * The progression is binary, not gradual: nothing touches the CP before the last
     * level, so levels 1 to 3 are interchangeable for a PvP build.
     */
    public function testEveryLevelBelowTheLastGivesTheSameCpTarget(): void
    {
        $targets = array_map(fn(int $level): ?int => $this->megaTarget(new MegaLevel($level))?->cp->value, [1, 2, 3]);

        self::assertCount(1, array_unique($targets), 'levels 1 to 3 grant no CP boost at all');
        self::assertNotNull($targets[0]);
    }

    public function testTheResultCarriesWhatTheChosenLevelGrants(): void
    {
        $result = $this->megaHandler()(new RankPokemonQuery('gengar', megaLevel: new MegaLevel(4)));

        self::assertNotNull($result->megaLevel);
        self::assertNotNull($result->megaLevelEffects);
        self::assertSame(4, $result->megaLevel->value);
        self::assertSame(2, $result->megaLevelEffects->additionalLevels);
        self::assertSame(1, $result->megaLevelEffects->cooldownDays, 'the last level recharges daily');
    }

    /**
     * The extra Charged Attack is recognised by the game's own flag, not by the "+" its
     * name happens to end with.
     */
    public function testTheExclusiveMoveIsPickedOutOfTheMoveset(): void
    {
        $moves = new InMemoryMoveCatalog(
            new Move(new MoveId('FOUL_PLAY'), 70, 45),
            new Move(new MoveId('PSYBEAM_PLUS'), 60, 45, megaExclusive: true),
        );

        $rankings = new InMemoryPvpRankingCatalog([
            League::MegaGreat->value => [
                'gengar_mega' => new SpeciesRank(League::MegaGreat, 27, 1200, 90.2, new Moveset(new MoveId('PSYWAVE'), [
                    new MoveId('FOUL_PLAY'),
                    new MoveId('PSYBEAM_PLUS'),
                ])),
            ],
        ]);

        $catalog = new InMemorySpeciesCatalog([$this->species('gengar', 94), $this->megaSpecies()]);

        $result = (new RankPokemonHandler(
            new SpeciesResolver($catalog),
            $catalog,
            $rankings,
            $this->ivRanker(),
            $this->cpCalculator(),
            new FixedMegaLevelTable(),
            new ExclusiveMoveFinder($moves),
        ))(new RankPokemonQuery('gengar', megaLevel: MegaLevel::default()));

        self::assertNotNull($result->exclusiveMove);
        self::assertSame('PSYBEAM_PLUS', $result->exclusiveMove->id->value);
        self::assertSame(60, $result->exclusiveMove->power);
    }

    /**
     * The gain shifts with the IVs: base and mega forms have different base stats, so the
     * same IVs weigh differently on each. One figure per species would be wrong.
     */
    public function testTheMegaCpGainIsMeasuredForTheGivenIvs(): void
    {
        $lowAttack = $this->megaGainFor(new IvSpread(0, 15, 15));
        $perfect = $this->megaGainFor(new IvSpread(15, 15, 15));

        self::assertGreaterThan(0, $perfect);
        self::assertNotSame($perfect, $lowAttack, 'the gain is not a species constant');
    }

    /**
     * At the last Mega Level the two free levels land on top of the stat gain, so the
     * step from the powered-up CP to the evolved one is bigger — measuring both forms at
     * the same level would report the same figure at every level and understate it here.
     */
    public function testTheLastMegaLevelShowsABiggerGainThanTheOnesBelow(): void
    {
        $iv = new IvSpread(0, 15, 15);
        $atThree = $this->megaGainFor($iv, MegaLevel::default());
        $atFour = $this->megaGainFor($iv, new MegaLevel(4));

        self::assertNotNull($atThree);
        self::assertNotNull($atFour);
        self::assertGreaterThan($atThree, $atFour, 'the two free levels count towards the gain');
    }

    private function megaGainFor(IvSpread $iv, ?MegaLevel $level = null): ?int
    {
        return $this->megaHandler()(new RankPokemonQuery('gengar', $iv, megaLevel: $level ?? MegaLevel::default()))
            ->outcomeFor(League::MegaGreat)
            ?->powerUpTo
            ?->megaGainPercent;
    }

    private function megaTarget(MegaLevel $level): ?PowerUpTarget
    {
        return $this->megaHandler()(new RankPokemonQuery('gengar', new IvSpread(0, 15, 15), megaLevel: $level))
            ->outcomeFor(League::MegaGreat)?->powerUpTo;
    }

    public function testANonMegaHasNothingToPowerUpTowards(): void
    {
        $outcome = $this->handler()(new RankPokemonQuery('gengar', new IvSpread(0, 15, 15), [League::Great]))
            ->outcomeFor(League::Great);

        self::assertNull($outcome?->powerUpTo);
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
            (new RankPokemonHandler(
                new SpeciesResolver($catalog),
                $catalog,
                new InMemoryPvpRankingCatalog(),
                $this->ivRanker(),
                $this->cpCalculator(),
                new FixedMegaLevelTable(),
                new ExclusiveMoveFinder(new InMemoryMoveCatalog()),
            ))(new RankPokemonQuery('487'));
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

        $result = (new RankPokemonHandler(
            new SpeciesResolver($catalog),
            $catalog,
            new InMemoryPvpRankingCatalog(),
            $this->ivRanker(),
            $this->cpCalculator(),
            new FixedMegaLevelTable(),
            new ExclusiveMoveFinder(new InMemoryMoveCatalog()),
        ))(new RankPokemonQuery('tinkaton', leagues: [League::Great], shadow: true));

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

        return new RankPokemonHandler(
            new SpeciesResolver($catalog),
            $catalog,
            $rankings,
            $this->ivRanker(),
            $this->cpCalculator(),
            new FixedMegaLevelTable(),
            new ExclusiveMoveFinder(new InMemoryMoveCatalog()),
        );
    }

    private function megaHandler(): RankPokemonHandler
    {
        $catalog = new InMemorySpeciesCatalog([
            $this->species('gengar', 94),
            $this->megaSpecies(),
        ]);

        return new RankPokemonHandler(
            new SpeciesResolver($catalog),
            $catalog,
            new InMemoryPvpRankingCatalog(),
            $this->ivRanker(),
            $this->cpCalculator(),
            new FixedMegaLevelTable(),
            new ExclusiveMoveFinder(new InMemoryMoveCatalog()),
        );
    }

    private function megaSpecies(): Species
    {
        return new Species(
            new SpeciesId('gengar_mega'),
            new DexNumber(94),
            new BaseStats(349, 199, 155),
            shadowEligible: false,
            superMega: false,
            forms: ['mega'],
        );
    }

    private function ivRanker(): IvRankCalculator
    {
        $multipliers = new RealCpMultiplierTable();

        return new IvRankCalculator(new CpCalculator($multipliers), $multipliers);
    }

    private function cpCalculator(): CpCalculator
    {
        return new CpCalculator(new RealCpMultiplierTable());
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
