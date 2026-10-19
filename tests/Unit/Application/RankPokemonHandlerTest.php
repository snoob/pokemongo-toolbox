<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application;

use App\Application\Pvp\RankPokemon\EvolutionRanker;
use App\Application\Pvp\RankPokemon\EvolutionRanking;
use App\Application\Pvp\RankPokemon\LeagueOutcome;
use App\Application\Pvp\RankPokemon\RankPokemonHandler;
use App\Application\Pvp\RankPokemon\RankPokemonQuery;
use App\Domain\Pokemon\Model\BaseStats;
use App\Domain\Pokemon\Model\DexNumber;
use App\Domain\Pokemon\Model\IvSpread;
use App\Domain\Pokemon\Model\Move;
use App\Domain\Pokemon\Model\MoveId;
use App\Domain\Pokemon\Model\Species;
use App\Domain\Pokemon\Model\SpeciesId;
use App\Domain\Pokemon\Service\EvolutionTree;
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
        $result = $this->handler()(new RankPokemonQuery('Ectoplasma', leagues: [League::Great, League::Ultra]));
        $gengar = $result->rankingOf('gengar');

        self::assertSame('gengar', $result->asked->id->value);
        self::assertNotNull($gengar);
        self::assertCount(2, $gengar->outcomes);
        self::assertSame(615, $gengar->outcomeFor(League::Great)?->speciesRank?->position);
        self::assertNull(
            $gengar->outcomeFor(League::Ultra)?->speciesRank,
            'an unranked league reports nothing rather than a zero',
        );
    }

    public function testATypoIsRankedAsTheNearestNameAndRemembered(): void
    {
        $result = $this->handler()(new RankPokemonQuery('Ectoplsama'));

        self::assertSame('gengar', $result->asked->id->value);
        self::assertSame('Ectoplsama', $result->guessedFrom);
    }

    public function testAnExactNameLeavesNothingToOwnUpTo(): void
    {
        self::assertNull($this->handler()(new RankPokemonQuery('Ectoplasma'))->guessedFrom);
    }

    public function testItResolvesADexNumberToTheDefaultForm(): void
    {
        self::assertSame('gengar', $this->handler()(new RankPokemonQuery('94'))->asked->id->value);
    }

    public function testItRanksTheGivenIvsWithinTheSpecies(): void
    {
        $outcome = $this->outcome(
            new RankPokemonQuery('gengar', new IvSpread(1, 15, 14), [League::Great]),
            'gengar',
            League::Great,
        );

        self::assertNotNull($outcome);
        self::assertSame(131, $outcome->spread?->position);
        self::assertFalse($outcome->ineligible);
    }

    public function testTheBestSpreadIsReportedEvenWithoutIvs(): void
    {
        $outcome = $this->outcome(new RankPokemonQuery('gengar', leagues: [League::Great]), 'gengar', League::Great);

        self::assertNull($outcome?->spread, 'no IVs given, so nothing to place');
        self::assertSame('0/13/13', (string) $outcome?->best?->iv, 'the spread worth hunting is still answered');
        self::assertSame(1, $outcome?->best?->position);
    }

    /**
     * Evolving keeps the IVs and the level, so the form in hand is ranked as everything
     * it can become, each evolution followed by its mega.
     */
    public function testABaseFormIsRankedAsEveryFormItCanBecome(): void
    {
        $rankings = new InMemoryPvpRankingCatalog([
            League::Great->value => [
                'gastly' => new SpeciesRank(League::Great, 1100, 1146, 30.0),
                'haunter' => new SpeciesRank(League::Great, 900, 1146, 50.0),
            ],
        ]);

        self::assertSame(
            ['gastly', 'haunter', 'gengar', 'gengar_mega'],
            $this->ids($this->handler($rankings)(new RankPokemonQuery('gastly'))->evolutions),
        );
    }

    /**
     * A form that still evolves and that no league ranks would only fill a table with
     * "unranked" — the form in hand included: the power-up column of the next tables
     * already answers for it.
     */
    public function testAnUnrankedFormThatStillEvolvesIsLeftOut(): void
    {
        self::assertSame(
            ['gengar', 'gengar_mega'],
            $this->ids($this->handler()(new RankPokemonQuery('gastly'))->evolutions),
        );
    }

    public function testTheFormInHandStaysWhenALeagueRanksIt(): void
    {
        $rankings = new InMemoryPvpRankingCatalog([
            League::Great->value => ['gastly' => new SpeciesRank(League::Great, 1100, 1146, 30.0)],
        ]);

        self::assertSame(
            ['gastly', 'gengar', 'gengar_mega'],
            $this->ids($this->handler($rankings)(new RankPokemonQuery('gastly'))->evolutions),
        );
    }

    /**
     * A final form is the answer itself; leaving it out would hide that it ranks nowhere.
     */
    public function testAnUnrankedFinalFormStays(): void
    {
        self::assertSame(
            ['gengar', 'gengar_mega'],
            $this->ids($this->handler(new InMemoryPvpRankingCatalog())(new RankPokemonQuery('gengar'))->evolutions),
        );
    }

    public function testAFinalFormIsRankedWithItsMega(): void
    {
        self::assertSame(
            ['gengar', 'gengar_mega'],
            $this->ids($this->handler()(new RankPokemonQuery('gengar'))->evolutions),
        );
    }

    public function testAMegaIsRankedInTheMegaEditionsAndTheRestInTheStandardLeagues(): void
    {
        $result = $this->handler()(new RankPokemonQuery('gengar'));

        self::assertSame(['great', 'ultra', 'master'], $this->leagues($result->rankingOf('gengar')));
        self::assertSame(
            ['mega-great', 'mega-ultra', 'mega-master'],
            $this->leagues($result->rankingOf('gengar_mega')),
            'a mega cannot enter a standard league, so ranking it there would answer nothing',
        );
    }

    /**
     * Naming a standard league leaves the megas out rather than ranking them where they
     * may not enter.
     */
    public function testANamedStandardLeagueLeavesTheMegasOut(): void
    {
        $result = $this->handler()(new RankPokemonQuery('gengar', leagues: [League::Great]));

        self::assertSame(['gengar'], $this->ids($result->evolutions));
    }

    /**
     * A Mega Edition allows one mega per team, the rest being ordinary Pokémon: both are
     * ranked there.
     */
    public function testANamedMegaEditionKeepsTheWholeFamily(): void
    {
        $result = $this->handler()(new RankPokemonQuery('gengar', leagues: [League::MegaGreat]));

        self::assertSame(['gengar', 'gengar_mega'], $this->ids($result->evolutions));
        self::assertSame(['mega-great'], $this->leagues($result->rankingOf('gengar')));
    }

    public function testAMegaAskedByItsIdStillAnswersInAStandardLeague(): void
    {
        $result = $this->handler()(new RankPokemonQuery('gengar_mega', leagues: [League::Great]));

        self::assertSame(['gengar_mega'], $this->ids($result->evolutions));
        self::assertSame(['great'], $this->leagues($result->evolutions[0]));
    }

    /**
     * A league caps the CP of the form that fights. The figure the game shows while
     * powering up is the one of the form in hand, at the same level.
     */
    public function testAnEvolutionReportsTheCpOfTheFormInHandToPowerUpTo(): void
    {
        $outcome = $this->outcome(
            new RankPokemonQuery('gastly', new IvSpread(0, 15, 15), [League::Great]),
            'gengar',
            League::Great,
        );

        self::assertNotNull($outcome?->spread);
        self::assertNotNull($outcome->powerUpTo);
        self::assertSame($outcome->spread->level->value, $outcome->powerUpTo->level->value, 'evolving keeps the level');
        self::assertLessThan($outcome->spread->cp->value, $outcome->powerUpTo->cp->value);
        self::assertNull($outcome->powerUpTo->megaGainPercent, 'a gain is only told one step from a mega');
    }

    public function testTheFormInHandHasNothingToPowerUpTowards(): void
    {
        $outcome = $this->outcome(
            new RankPokemonQuery('gengar', new IvSpread(0, 15, 15), [League::Great]),
            'gengar',
            League::Great,
        );

        self::assertNull($outcome?->powerUpTo);
        self::assertNull($outcome?->bestPowerUpTo);
    }

    public function testAMegaReportsTheCpBeforeMegaEvolvingAndWhatItAdds(): void
    {
        $outcome = $this->outcome(
            new RankPokemonQuery('gengar', new IvSpread(0, 15, 15), [League::MegaGreat]),
            'gengar_mega',
            League::MegaGreat,
        );

        self::assertNotNull($outcome?->powerUpTo);
        self::assertLessThan(1500, $outcome->powerUpTo->cp->value, 'the base form reads less than the capped mega');
        self::assertGreaterThan(0, $outcome->powerUpTo->megaGainPercent);
    }

    /**
     * From two evolutions back, a gain would mostly measure the ordinary evolutions; the
     * figure to stop at is still the one of the form in hand.
     */
    public function testAMegaReachedFromFurtherBackHasATargetButNoGain(): void
    {
        $outcome = $this->outcome(
            new RankPokemonQuery('gastly', new IvSpread(0, 15, 15), [League::MegaGreat]),
            'gengar_mega',
            League::MegaGreat,
        );

        self::assertNotNull($outcome?->powerUpTo);
        self::assertNull($outcome->powerUpTo->megaGainPercent);
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
        $result = $this->handler()(
            new RankPokemonQuery('gengar', leagues: [League::MegaGreat], megaLevel: new MegaLevel(4)),
        );

        self::assertNotNull($result->megaLevelEffects);
        self::assertSame(4, $result->megaLevel?->value);
        self::assertSame(2, $result->megaLevelEffects->additionalLevels);
        self::assertSame(1, $result->megaLevelEffects->cooldownDays, 'the last level recharges daily');
    }

    public function testWithoutAMegaInTheFamilyNoMegaLevelIsReported(): void
    {
        $result = $this->handler()(new RankPokemonQuery('gengar', leagues: [League::Great]));

        self::assertNull($result->megaLevel);
        self::assertNull($result->megaLevelEffects);
    }

    /**
     * The extra Charged Attack is recognised by the game's own flag, not by the "+" its
     * name happens to end with — and it belongs to the mega, not to the whole family.
     */
    public function testTheExclusiveMoveIsPickedOutOfTheMegasMoveset(): void
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

        $result = $this->handler($rankings, $moves)(
            new RankPokemonQuery('gengar', leagues: [League::Great, League::MegaGreat]),
        );

        self::assertNull($result->rankingOf('gengar')?->exclusiveMove);
        self::assertSame('PSYBEAM_PLUS', $result->rankingOf('gengar_mega')?->exclusiveMove?->id->value);
        self::assertSame(60, $result->rankingOf('gengar_mega')?->exclusiveMove?->power);
    }

    /**
     * The gain shifts with the IVs: base and mega forms have different base stats, so the
     * same IVs weigh differently on each. One figure per species would be wrong.
     */
    public function testTheMegaCpGainIsMeasuredForTheGivenIvs(): void
    {
        $lowAttack = $this->megaTarget(MegaLevel::default(), new IvSpread(0, 15, 15))?->megaGainPercent;
        $perfect = $this->megaTarget(MegaLevel::default(), new IvSpread(15, 15, 15))?->megaGainPercent;

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
        $atThree = $this->megaTarget(MegaLevel::default())?->megaGainPercent;
        $atFour = $this->megaTarget(new MegaLevel(4))?->megaGainPercent;

        self::assertNotNull($atThree);
        self::assertNotNull($atFour);
        self::assertGreaterThan($atThree, $atFour, 'the two free levels count towards the gain');
    }

    /**
     * A shadow keeps its shadow when it evolves, and has to be purified — rerolling its
     * IVs — before it may Mega Evolve.
     */
    public function testAShadowIsRankedAsTheShadowsItBecomesWithoutTheMega(): void
    {
        $result = $this->handler()(new RankPokemonQuery('haunter', leagues: [League::Great], shadow: true));

        self::assertSame('haunter_shadow', $result->asked->id->value);
        // The unranked shadow Haunter in hand is left out; what it becomes is still a shadow.
        self::assertSame(['gengar_shadow'], $this->ids($result->evolutions));
    }

    private function megaTarget(MegaLevel $level, ?IvSpread $iv = null): ?PowerUpTarget
    {
        return $this->outcome(
            new RankPokemonQuery('gengar', $iv ?? new IvSpread(0, 15, 15), [League::MegaGreat], megaLevel: $level),
            'gengar_mega',
            League::MegaGreat,
        )?->powerUpTo;
    }

    private function outcome(RankPokemonQuery $query, string $speciesId, League $league): ?LeagueOutcome
    {
        return $this->handler()($query)->rankingOf($speciesId)?->outcomeFor($league);
    }

    /**
     * @param list<EvolutionRanking> $rankings
     *
     * @return list<string>
     */
    private function ids(array $rankings): array
    {
        return array_map(static fn(EvolutionRanking $r): string => $r->species()->id->value, $rankings);
    }

    /**
     * @return list<string>
     */
    private function leagues(?EvolutionRanking $ranking): array
    {
        return array_map(static fn(LeagueOutcome $o): string => $o->league->value, $ranking->outcomes ?? []);
    }

    private function handler(
        ?InMemoryPvpRankingCatalog $rankings = null,
        ?InMemoryMoveCatalog $moves = null,
    ): RankPokemonHandler {
        // Real base stats, so the CP targets are the game's.
        $catalog = new InMemorySpeciesCatalog([
            $this->species('gastly', 92, new BaseStats(186, 67, 102), evolutions: ['haunter']),
            $this->species('haunter', 93, new BaseStats(223, 107, 128), evolutions: ['gengar']),
            $this->species('haunter_shadow', 93, new BaseStats(223, 107, 128), ['shadow']),
            $this->species('gengar', 94, new BaseStats(261, 149, 155)),
            $this->species('gengar_shadow', 94, new BaseStats(261, 149, 155), ['shadow']),
            $this->species('gengar_mega', 94, new BaseStats(349, 199, 155), ['mega']),
        ], [
            'gengar' => ['Gengar', 'Ectoplasma'],
            'gengar_shadow' => ['Gengar (Shadow)', 'Ectoplasma (Obscur)'],
            'gengar_mega' => ['Gengar (Mega)', 'Ectoplasma (Méga)'],
        ]);

        $multipliers = new RealCpMultiplierTable();

        return new RankPokemonHandler(
            new SpeciesResolver($catalog),
            new EvolutionTree($catalog),
            new EvolutionRanker(
                $rankings ?? new InMemoryPvpRankingCatalog([
                    League::Great->value => ['gengar' => new SpeciesRank(League::Great, 615, 1146, 73.0)],
                ]),
                new IvRankCalculator(new CpCalculator($multipliers), $multipliers),
                new CpCalculator($multipliers),
                new FixedMegaLevelTable(),
                new ExclusiveMoveFinder($moves ?? new InMemoryMoveCatalog()),
            ),
            new FixedMegaLevelTable(),
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
