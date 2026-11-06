<?php

declare(strict_types=1);

namespace App\Tests\Unit\Presenter;

use App\Application\Stardust\StardustInfo\StardustInfoHandler;
use App\Domain\Stardust\Service\BattleLeagueStardust;
use App\Domain\Stardust\Service\BerryStardust;
use App\Domain\Stardust\Service\CatchStardust;
use App\UI\Cli\Presenter\StardustInfoTextPresenter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

final class StardustInfoTextPresenterTest extends TestCase
{
    public function testEveryAmountShowsWhatTheStarPieceAdds(): void
    {
        $output = $this->render();

        self::assertMatchesRegularExpression(
            '/100\s+10,000\s+15,000 \(\+ 5,000\)\s+12,500\s+18,700 \(\+ 6,200\)/',
            $output,
        );
        self::assertMatchesRegularExpression('/Set with 5 wins\s+14,400\s+21,600 \(\+ 7,200\)/', $output);
        self::assertMatchesRegularExpression('/Season end, rank Legend\s+200,000\s+300,000 \(\+ 100,000\)/', $output);
    }

    public function testTheGymShowsWhatEachSizePays(): void
    {
        $output = $this->render();

        self::assertMatchesRegularExpression(
            '/3\s+10\s+7,650\s+11,475 \(\+ 3,825\)\s+13,950\s+20,925 \(\+ 6,975\)/',
            $output,
        );
        self::assertMatchesRegularExpression(
            '/6\s+10\s+16,650\s+24,975 \(\+ 8,325\)\s+54,900\s+82,350 \(\+ 27,450\)/',
            $output,
        );
    }

    private function render(): string
    {
        $buffer = new BufferedOutput();
        $result = (new StardustInfoHandler(new CatchStardust(), new BattleLeagueStardust(), new BerryStardust()))();

        new StardustInfoTextPresenter()->present(new SymfonyStyle(new ArrayInput([]), $buffer), $result);

        return $buffer->fetch();
    }
}
