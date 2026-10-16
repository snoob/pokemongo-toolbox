<?php

declare(strict_types=1);

namespace App\Tests\Unit\Presenter;

use App\Application\Pvp\RankPokemon\EvolutionRanking;
use App\Application\Pvp\RankPokemon\LeagueOutcome;
use App\Application\Pvp\RankPokemon\RankPokemonResult;
use App\Domain\Pokemon\Model\BaseStats;
use App\Domain\Pokemon\Model\DexNumber;
use App\Domain\Pokemon\Model\EvolutionPath;
use App\Domain\Pokemon\Model\IvSpread;
use App\Domain\Pokemon\Model\MoveId;
use App\Domain\Pokemon\Model\Species;
use App\Domain\Pokemon\Model\SpeciesId;
use App\Domain\Pvp\Model\Cp;
use App\Domain\Pvp\Model\League;
use App\Domain\Pvp\Model\MegaLevel;
use App\Domain\Pvp\Model\Moveset;
use App\Domain\Pvp\Model\PokemonLevel;
use App\Domain\Pvp\Model\PowerUpTarget;
use App\Domain\Pvp\Model\RankedSpread;
use App\Domain\Pvp\Model\SpeciesRank;
use App\Infrastructure\Naming\MoveNameResolver;
use App\Infrastructure\Naming\SpeciesNameResolver;
use App\Tests\Fake\FixtureTranslator;
use App\UI\Cli\Presenter\BaseFormCellFormatter;
use App\UI\Cli\Presenter\IvCellFormatter;
use App\UI\Cli\Presenter\MegaFooterNotes;
use App\UI\Cli\Presenter\MovesetFormatter;
use App\UI\Cli\Presenter\PvpRankTextPresenter;
use App\UI\Cli\Presenter\RankFooterNotes;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

final class PvpRankTextPresenterTest extends TestCase
{
    public function testItShowsTheSpreadWorthHuntingBesideTheGivenOne(): void
    {
        $output = $this->render(new IvSpread(1, 15, 14), $this->spread(new IvSpread(1, 15, 14), 131));

        self::assertStringContainsString('1/15/14', $output);
        self::assertStringContainsString('0/13/13', $output);
        self::assertStringContainsString('best', $output);
    }

    public function testTheBestLineIsDroppedWhenTheGivenIvsAlreadyAreTheBest(): void
    {
        $best = new IvSpread(0, 13, 13);
        $output = $this->render($best, $this->spread($best, 1));

        self::assertStringContainsString('0/13/13', $output);
        self::assertStringNotContainsString('best', $output, 'repeating the same spread teaches nothing');
    }

    public function testWithoutIvsOnlyTheBestIsShown(): void
    {
        $output = $this->render(null, null);

        self::assertStringContainsString('0/13/13', $output);
        self::assertStringContainsString('best', $output);
    }

    public function testAnIneligibleLeagueSaysSoInsteadOfShowingSpreads(): void
    {
        $outcome = new LeagueOutcome(League::Great, null, null, null, ineligible: true);
        $output = $this->renderOutcome(null, $outcome);

        self::assertStringContainsString('ineligible', $output);
        self::assertStringNotContainsString('best', $output);
    }

    public function testASpreadThatCannotBeBeatenIsShownInGreen(): void
    {
        $best = new IvSpread(0, 13, 13);
        $output = $this->render($best, $this->spread($best, 1), decorated: true);

        self::assertStringContainsString("\033[32m0/13/13\033[39m", $output);
    }

    public function testAnOrdinarySpreadIsNotHighlightedButTheBestOneIs(): void
    {
        $output = $this->render(new IvSpread(1, 15, 14), $this->spread(new IvSpread(1, 15, 14), 131), decorated: true);

        self::assertStringNotContainsString("\033[32m1/15/14", $output, 'rank 131 is nothing to celebrate');
        self::assertStringContainsString("\033[32m0/13/13\033[39m", $output);
    }

    public function testTheRecommendedMovesetIsShownBesideTheRank(): void
    {
        $output = $this->render(null, null);

        self::assertStringContainsString('Griffe Ombre', $output, 'the fast move, in the display locale');
        self::assertStringContainsString('Poing Ombre', $output);
        self::assertStringContainsString('Vibrobscur', $output);
    }

    public function testAMoveCostingAnEliteTmIsMarkedAndExplained(): void
    {
        $output = $this->render(null, null);

        self::assertStringContainsString('Poing Ombre' . MovesetFormatter::ELITE_MARK, $output);
        self::assertStringNotContainsString(
            'Vibrobscur' . MovesetFormatter::ELITE_MARK,
            $output,
            'only elite moves carry the mark',
        );
        self::assertStringContainsString('Elite TM', $output, 'the legend explains the mark');
    }

    public function testWithoutAnEliteMoveTheLegendStaysOut(): void
    {
        $outcome = new LeagueOutcome(League::Great, new SpeciesRank(League::Great, 615, 1146, 73.0, new Moveset(
            new MoveId('SHADOW_CLAW'),
            [new MoveId('DARK_PULSE')],
        )), null, $this->spread(new IvSpread(0, 13, 13), 1));

        self::assertStringNotContainsString('Elite TM', $this->renderOutcome(null, $outcome));
    }

    public function testTheFormInHandHasNoPowerUpColumn(): void
    {
        self::assertStringNotContainsString(' CP ', $this->firstLine($this->render(null, null), 'League'));
    }

    /**
     * Evolving keeps the level, so the column gives the CP of the form in hand — named
     * after it, since that is the number on screen while powering up.
     */
    public function testAnEvolutionGetsAColumnNamedAfterTheFormInHand(): void
    {
        $outcome = new LeagueOutcome(
            league: League::Great,
            speciesRank: new SpeciesRank(League::Great, 615, 1146, 73.0),
            spread: null,
            best: $this->spread(new IvSpread(0, 13, 13), 1),
            bestPowerUpTo: new PowerUpTarget(new Cp(1102), new PokemonLevel(19.5)),
        );

        $output = $this->renderOutcome(null, $outcome, path: [$this->haunter(), $this->gengar()]);

        self::assertStringContainsString('Spectrum CP', $output);
        self::assertStringContainsString('1102 CP (lvl 19.5)', $output);
        self::assertStringNotContainsString('%', $this->firstLine($output, '1102 CP'), 'no gain outside a mega step');
        self::assertStringContainsString('Spectrum CP = what to power Spectrum up to before evolving.', $output);
    }

    /**
     * The title names the form ranked in the table, nothing more: the form in hand is
     * already named by the power-up column.
     */
    public function testAnEvolutionIsTitledWithItsOwnNameOnly(): void
    {
        $output = $this->renderOutcome(null, $this->greatOutcome(null), path: [$this->haunter(), $this->gengar()]);

        self::assertStringContainsString('Ectoplasma — #94', $output);
        self::assertStringNotContainsString('→ Ectoplasma', $output);
    }

    public function testTheFormInHandIsTitledWithItsPokedexNumber(): void
    {
        self::assertStringContainsString('Ectoplasma — #94', $this->render(null, null));
    }

    public function testAMegaTitleCarriesTheMegaLevelItIsRankedAt(): void
    {
        $path = new EvolutionPath([$this->gengar(), $this->megaGengar()]);
        $output = $this->present(
            new RankPokemonResult(
                $this->gengar(),
                null,
                PokemonLevel::regularCap(),
                [new EvolutionRanking($path, [$this->greatOutcome(null)])],
                new MegaLevel(2),
            ),
        );

        self::assertStringContainsString('Ectoplasma (Méga, level 2) — #94', $output);
    }

    public function testAMegaOneStepAwayAlsoTellsWhatMegaEvolvingAdds(): void
    {
        $outcome = new LeagueOutcome(
            league: League::MegaGreat,
            speciesRank: new SpeciesRank(League::MegaGreat, 262, 1200, 81.3),
            spread: null,
            best: $this->spread(new IvSpread(0, 8, 15), 1),
            powerUpTo: new PowerUpTarget(new Cp(916), new PokemonLevel(18.0), megaGainPercent: 64),
        );

        $output = $this->renderOutcome(null, $outcome, path: [$this->gengar(), $this->megaGengar()]);

        self::assertStringContainsString('Ectoplasma CP', $output, 'the column earns its width here');
        self::assertStringContainsString('916 CP (lvl 18) +64 %', $output);
        self::assertStringContainsString('and what Mega Evolving adds at that level', $output);
    }

    /**
     * Without a CP cap every spread reaches the level cap, so the level is the same
     * number on every row and says nothing.
     */
    public function testAnUncappedLeagueDropsTheLevelAndThePowerUpColumn(): void
    {
        $outcome = new LeagueOutcome(
            League::MegaMaster,
            new SpeciesRank(League::MegaMaster, 126, 466, 63.4),
            null,
            $this->spread(new IvSpread(15, 15, 15), 1),
        );

        $output = $this->renderOutcome(null, $outcome, path: [$this->gengar(), $this->megaGengar()]);

        self::assertStringContainsString('CP 1498', $output);
        self::assertStringNotContainsString('at level', $output);
        self::assertStringNotContainsString('Ectoplasma CP', $output, 'nothing to aim for without a cap');
    }

    public function testThePowerUpColumnMirrorsTheIvColumnAndGreensTheBest(): void
    {
        $outcome = new LeagueOutcome(
            league: League::MegaUltra,
            speciesRank: new SpeciesRank(League::MegaUltra, 99, 904, 85.9),
            spread: $this->spread(new IvSpread(0, 8, 15), 733),
            best: $this->spread(new IvSpread(0, 15, 15), 1),
            powerUpTo: new PowerUpTarget(new Cp(1502), new PokemonLevel(29.5), megaGainPercent: 64),
            bestPowerUpTo: new PowerUpTarget(new Cp(1527), new PokemonLevel(29.5), megaGainPercent: 64),
        );

        $output = $this->renderOutcome(
            new IvSpread(0, 8, 15),
            $outcome,
            decorated: true,
            path: [$this->gengar(), $this->megaGengar()],
        );

        self::assertStringContainsString('1502 CP (lvl 29.5) +64 %', $output, 'the given spread, plain');
        self::assertStringContainsString(
            "\033[32m1527 CP (lvl 29.5) +64 %\033[39m",
            $output,
            'the unbeatable one, in green, level included',
        );
    }

    public function testEachFormOfTheFamilyGetsItsOwnTableAndTheNotesComeOnce(): void
    {
        $result = new RankPokemonResult($this->haunter(), null, PokemonLevel::regularCap(), [
            new EvolutionRanking(new EvolutionPath([$this->haunter()]), [$this->greatOutcome(null)]),
            new EvolutionRanking(new EvolutionPath([$this->haunter(), $this->gengar()]), [$this->greatOutcome(null)]),
        ]);

        $output = $this->present($result);

        self::assertStringContainsString('Spectrum — #93', $output);
        self::assertStringContainsString('Ectoplasma — #94', $output);
        self::assertLessThan(
            strpos($output, 'Ectoplasma — '),
            strpos($output, 'Spectrum — '),
            'the form in hand first',
        );
        self::assertSame(1, substr_count($output, 'Level cap considered'));
    }

    private function render(?IvSpread $iv, ?RankedSpread $spread, bool $decorated = false): string
    {
        return $this->renderOutcome($iv, $this->greatOutcome($spread), $decorated);
    }

    private function greatOutcome(?RankedSpread $spread): LeagueOutcome
    {
        return new LeagueOutcome(League::Great, new SpeciesRank(League::Great, 615, 1146, 73.0, new Moveset(
            new MoveId('SHADOW_CLAW'),
            [new MoveId('SHADOW_PUNCH'), new MoveId('DARK_PULSE')],
        )), $spread, $this->spread(new IvSpread(0, 13, 13), 1));
    }

    /**
     * @param non-empty-list<Species>|null $path from the form in hand to the one ranked; Gengar alone by default
     */
    private function renderOutcome(
        ?IvSpread $iv,
        LeagueOutcome $outcome,
        bool $decorated = false,
        ?array $path = null,
    ): string {
        $path = new EvolutionPath($path ?? [$this->gengar()]);

        return $this->present(new RankPokemonResult($path->first(), $iv, PokemonLevel::regularCap(), [
            new EvolutionRanking($path, [$outcome]),
        ]), $decorated);
    }

    private function present(RankPokemonResult $result, bool $decorated = false): string
    {
        $output = new BufferedOutput();
        $output->setDecorated($decorated);
        $translator = FixtureTranslator::create();
        $names = new SpeciesNameResolver($translator);
        $movesets = new MovesetFormatter(new MoveNameResolver($translator));
        $baseForms = new BaseFormCellFormatter($names);
        $presenter = new PvpRankTextPresenter(
            $names,
            $movesets,
            new IvCellFormatter(),
            $baseForms,
            new RankFooterNotes(
                $baseForms,
                $movesets,
                new MegaFooterNotes(new MoveNameResolver($translator), $names),
                $names,
            ),
        );

        $presenter->present(new SymfonyStyle(new ArrayInput([]), $output), $result);

        return $output->fetch();
    }

    private function firstLine(string $output, string $needle): string
    {
        foreach (explode("\n", $output) as $line) {
            if (str_contains($line, $needle)) {
                return $line;
            }
        }

        self::fail(\sprintf('No line holds "%s".', $needle));
    }

    private function gengar(): Species
    {
        return new Species(
            new SpeciesId('gengar'),
            new DexNumber(94),
            new BaseStats(261, 149, 155),
            shadowEligible: true,
            eliteMoves: [new MoveId('SHADOW_PUNCH')],
        );
    }

    private function megaGengar(): Species
    {
        return new Species(
            new SpeciesId('gengar_mega'),
            new DexNumber(94),
            new BaseStats(349, 199, 155),
            shadowEligible: false,
            forms: ['mega'],
        );
    }

    private function haunter(): Species
    {
        return new Species(
            new SpeciesId('haunter'),
            new DexNumber(93),
            new BaseStats(223, 107, 128),
            shadowEligible: true,
        );
    }

    private function spread(IvSpread $iv, int $position): RankedSpread
    {
        return new RankedSpread(
            iv: $iv,
            position: $position,
            total: IvSpread::COMBINATIONS,
            statProduct: 1_456_564.0,
            percentOfBest: 1 === $position ? 100.0 : 98.02,
            cp: new Cp(1498),
            level: new PokemonLevel(19.5),
        );
    }
}
