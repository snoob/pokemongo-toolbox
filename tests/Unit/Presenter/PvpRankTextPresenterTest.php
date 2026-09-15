<?php

declare(strict_types=1);

namespace App\Tests\Unit\Presenter;

use App\Application\Pvp\RankPokemon\LeagueOutcome;
use App\Application\Pvp\RankPokemon\RankPokemonResult;
use App\Domain\Pokemon\Model\BaseStats;
use App\Domain\Pokemon\Model\DexNumber;
use App\Domain\Pokemon\Model\IvSpread;
use App\Domain\Pokemon\Model\MoveId;
use App\Domain\Pokemon\Model\Species;
use App\Domain\Pokemon\Model\SpeciesId;
use App\Domain\Pvp\Model\Cp;
use App\Domain\Pvp\Model\League;
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

    public function testTheBaseFormColumnOnlyAppearsForAMega(): void
    {
        self::assertStringNotContainsString('Hors méga', $this->render(null, null));
    }

    public function testAMegaGetsItsOwnBaseFormColumn(): void
    {
        $outcome = new LeagueOutcome(
            league: League::MegaGreat,
            speciesRank: new SpeciesRank(League::MegaGreat, 262, 1200, 81.3),
            spread: null,
            best: $this->spread(new IvSpread(0, 8, 15), 1),
            powerUpTo: new PowerUpTarget(new Cp(916), new PokemonLevel(18.0), megaGainPercent: 64),
        );

        $output = $this->renderOutcome(null, $outcome);

        self::assertStringContainsString('Pre-Mega CP', $output, 'the column earns its width here');
        self::assertStringContainsString('916 CP (lvl 18) +64 %', $output);
    }

    /**
     * Without a CP cap every spread reaches the level cap, so the level is the same
     * number on every row and says nothing.
     */
    public function testAnUncappedLeagueDropsTheLevelAndTheBaseFormCp(): void
    {
        $outcome = new LeagueOutcome(
            League::MegaMaster,
            new SpeciesRank(League::MegaMaster, 126, 466, 63.4),
            null,
            $this->spread(new IvSpread(15, 15, 15), 1),
        );

        $output = $this->renderOutcome(null, $outcome);

        self::assertStringContainsString('CP 1498', $output);
        self::assertStringNotContainsString('at level', $output);
        self::assertStringNotContainsString('Pre-Mega CP', $output, 'nothing to aim for without a cap');
    }

    public function testTheBaseFormColumnMirrorsTheIvColumnAndGreensTheBest(): void
    {
        $outcome = new LeagueOutcome(
            league: League::MegaUltra,
            speciesRank: new SpeciesRank(League::MegaUltra, 99, 904, 85.9),
            spread: $this->spread(new IvSpread(0, 8, 15), 733),
            best: $this->spread(new IvSpread(0, 15, 15), 1),
            powerUpTo: new PowerUpTarget(new Cp(1502), new PokemonLevel(29.5), megaGainPercent: 64),
            bestPowerUpTo: new PowerUpTarget(new Cp(1527), new PokemonLevel(29.5), megaGainPercent: 64),
        );

        $output = $this->renderOutcome(new IvSpread(0, 8, 15), $outcome, decorated: true);

        self::assertStringContainsString('1502 CP (lvl 29.5) +64 %', $output, 'the given spread, plain');
        self::assertStringContainsString(
            "\033[32m1527 CP (lvl 29.5) +64 %\033[39m",
            $output,
            'the unbeatable one, in green, level included',
        );
    }

    private function render(?IvSpread $iv, ?RankedSpread $spread, bool $decorated = false): string
    {
        return $this->renderOutcome(
            $iv,
            new LeagueOutcome(League::Great, new SpeciesRank(League::Great, 615, 1146, 73.0, new Moveset(
                new MoveId('SHADOW_CLAW'),
                [new MoveId('SHADOW_PUNCH'), new MoveId('DARK_PULSE')],
            )), $spread, $this->spread(new IvSpread(0, 13, 13), 1)),
            $decorated,
        );
    }

    private function renderOutcome(?IvSpread $iv, LeagueOutcome $outcome, bool $decorated = false): string
    {
        $species = new Species(
            new SpeciesId('gengar'),
            new DexNumber(94),
            new BaseStats(261, 149, 155),
            shadowEligible: true,
            eliteMoves: [new MoveId('SHADOW_PUNCH')],
        );

        $output = new BufferedOutput();
        $output->setDecorated($decorated);
        $translator = FixtureTranslator::create();
        $presenter = new PvpRankTextPresenter(
            new SpeciesNameResolver($translator),
            new MovesetFormatter(new MoveNameResolver($translator)),
            new IvCellFormatter(),
            new BaseFormCellFormatter(),
            new RankFooterNotes(
                new BaseFormCellFormatter(),
                new MovesetFormatter(new MoveNameResolver($translator)),
                new MoveNameResolver($translator),
            ),
        );

        $presenter->present(
            new SymfonyStyle(new ArrayInput([]), $output),
            new RankPokemonResult($species, $iv, PokemonLevel::regularCap(), [$outcome]),
        );

        return $output->fetch();
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
