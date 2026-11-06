<?php

declare(strict_types=1);

namespace App\UI\Cli\Presenter;

use App\Application\Stardust\StardustInfo\StardustInfoResult;
use App\Domain\Stardust\Model\GymFeeding;
use App\Domain\Stardust\Model\Stardust;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Style\SymfonyStyle;

final readonly class StardustInfoTextPresenter
{
    public function present(SymfonyStyle $io, StardustInfoResult $result): void
    {
        $io->title('Stardust');

        $this->catches($io, $result);
        $this->battleLeague($io, $result);
        $this->gym($io, $result->gym);
    }

    private function catches(SymfonyStyle $io, StardustInfoResult $result): void
    {
        $io->section('Catches');

        $rows = [];

        foreach ($result->catches as $reward) {
            $rows[] = [
                (string) $reward->catches,
                ...$this->amounts($reward->stardust),
                ...$this->amounts($reward->weatherBoosted),
            ];
        }

        $io->table(['Catches', 'Stardust', 'With Star Piece', 'Weather boosted', 'With Star Piece'], $rows);
    }

    private function battleLeague(SymfonyStyle $io, StardustInfoResult $result): void
    {
        $io->section('GO Battle League');

        $rows = [];

        foreach ($result->battleSets as $set) {
            $rows[] = [
                \sprintf('Set with %d win%s', $set->wins, 1 === $set->wins ? '' : 's'),
                ...$this->amounts($set->stardust),
            ];
        }

        $rows[] = new TableSeparator();

        foreach ($result->seasonEnd as $season) {
            $rows[] = [\sprintf('Season end, rank %s', $season->rank), ...$this->amounts($season->stardust)];
        }

        $io->table(['Reward', 'Stardust', 'With Star Piece'], $rows);
    }

    private function gym(SymfonyStyle $io, GymFeeding $gym): void
    {
        $io->section('Gym berries');

        $rows = [];

        foreach ($gym->defenders as $defender) {
            $rows[] = [
                (string) $defender->position,
                (string) $defender->berries,
                ...$this->amounts($defender->stardust),
                ...$this->amounts($gym->stardustUpTo($defender->position)),
            ];
        }

        $io->table([
            'Defender',
            'Berries',
            'Stardust',
            'With Star Piece',
            'Gym of this size',
            'With Star Piece',
        ], $rows);
        $io->text(
            '<comment>Switch to the next defender quickly: the streak carries over and keeps climbing.</comment>',
        );
    }

    /**
     * @return array{string, string}
     */
    private function amounts(Stardust $stardust): array
    {
        return [
            number_format($stardust->amount),
            \sprintf('%s (+ %s)', number_format($stardust->withStarPiece), number_format($stardust->starPieceBonus())),
        ];
    }
}
