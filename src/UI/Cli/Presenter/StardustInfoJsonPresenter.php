<?php

declare(strict_types=1);

namespace App\UI\Cli\Presenter;

use App\Application\Stardust\StardustInfo\StardustInfoResult;
use App\Domain\Stardust\Model\BattleSetReward;
use App\Domain\Stardust\Model\CatchReward;
use App\Domain\Stardust\Model\FedDefender;
use App\Domain\Stardust\Model\GymFeeding;
use App\Domain\Stardust\Model\SeasonReward;
use App\Domain\Stardust\Model\Stardust;

final readonly class StardustInfoJsonPresenter
{
    public function present(StardustInfoResult $result): string
    {
        $gym = $result->gym;

        return json_encode(
            [
                'catches' => array_map($this->catch(...), $result->catches),
                'battleSets' => array_map($this->battleSet(...), $result->battleSets),
                'seasonEnd' => array_map($this->season(...), $result->seasonEnd),
                'gym' => [
                    'defenders' => $this->defenders($gym),
                    'berries' => $gym->berries(),
                    'stardust' => $this->amounts($gym->stardust()),
                ],
            ],
            \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR,
        );
    }

    /**
     * @return array{catches: int, stardust: array{amount: int, withStarPiece: int}, weatherBoosted: array{amount: int, withStarPiece: int}}
     */
    private function catch(CatchReward $reward): array
    {
        return [
            'catches' => $reward->catches,
            'stardust' => $this->amounts($reward->stardust),
            'weatherBoosted' => $this->amounts($reward->weatherBoosted),
        ];
    }

    /**
     * @return array{wins: int, stardust: array{amount: int, withStarPiece: int}}
     */
    private function battleSet(BattleSetReward $set): array
    {
        return ['wins' => $set->wins, 'stardust' => $this->amounts($set->stardust)];
    }

    /**
     * @return array{rank: string, stardust: array{amount: int, withStarPiece: int}}
     */
    private function season(SeasonReward $season): array
    {
        return ['rank' => $season->rank, 'stardust' => $this->amounts($season->stardust)];
    }

    /**
     * @return list<array{position: int, berries: int, stardust: array{amount: int, withStarPiece: int}, gymOfThisSize: array{amount: int, withStarPiece: int}}>
     */
    private function defenders(GymFeeding $gym): array
    {
        $defenders = [];

        foreach ($gym->defenders as $defender) {
            $defenders[] = $this->defender($gym, $defender);
        }

        return $defenders;
    }

    /**
     * @return array{position: int, berries: int, stardust: array{amount: int, withStarPiece: int}, gymOfThisSize: array{amount: int, withStarPiece: int}}
     */
    private function defender(GymFeeding $gym, FedDefender $defender): array
    {
        return [
            'position' => $defender->position,
            'berries' => $defender->berries,
            'stardust' => $this->amounts($defender->stardust),
            'gymOfThisSize' => $this->amounts($gym->stardustUpTo($defender->position)),
        ];
    }

    /**
     * @return array{amount: int, withStarPiece: int}
     */
    private function amounts(Stardust $stardust): array
    {
        return ['amount' => $stardust->amount, 'withStarPiece' => $stardust->withStarPiece];
    }
}
