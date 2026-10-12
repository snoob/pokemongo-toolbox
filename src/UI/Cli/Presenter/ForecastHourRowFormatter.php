<?php

declare(strict_types=1);

namespace App\UI\Cli\Presenter;

use App\Application\Weather\ForecastIncense\ForecastIncenseResult;
use App\Application\Weather\ForecastIncense\HourOutcome;
use App\Domain\Pokemon\Model\SpeciesId;

/**
 * One line of the forecast: the hour — highlighted when it boosts at least one target —
 * the game weather, and a cell per target: ✔ boosted, – not.
 */
final readonly class ForecastHourRowFormatter
{
    /**
     * @return list<string>
     */
    public function format(HourOutcome $hour, ForecastIncenseResult $result): array
    {
        $time = $hour->hour->format('D H:i');
        $row = [
            $hour->boostsAny() ? \sprintf('<info>%s</info>', $time) : $time,
            $hour->weather?->label() ?? '<fg=gray>unknown</>',
        ];

        foreach ($result->targets as $target) {
            $row[] = $this->targetCell($hour, $target->id);
        }

        return $row;
    }

    private function targetCell(HourOutcome $hour, SpeciesId $target): string
    {
        if (!$hour->isKnown()) {
            return '<fg=gray>?</>';
        }

        return $hour->boosts($target) ? '<fg=green>✔</>' : '–';
    }
}
