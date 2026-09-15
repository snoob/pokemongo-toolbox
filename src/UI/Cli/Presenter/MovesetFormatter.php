<?php

declare(strict_types=1);

namespace App\UI\Cli\Presenter;

use App\Domain\Pokemon\Model\MoveId;
use App\Domain\Pokemon\Model\Species;
use App\Domain\Pvp\Model\Moveset;
use App\Infrastructure\Naming\MoveNameResolver;

/**
 * Renders a recommended moveset: the fast move, then the charged ones it pairs with.
 * Three charged moves means a mega carrying its extra move.
 */
final readonly class MovesetFormatter
{
    /** Marks a move that only an Elite TM unlocks — the asterisk PvP sites use for it. */
    public const string ELITE_MARK = '*';

    public function __construct(
        private MoveNameResolver $moves,
    ) {}

    public function format(Moveset $moveset, Species $species, ?string $locale): string
    {
        $charged = [];

        foreach ($moveset->charged as $move) {
            $charged[] = $this->label($move, $species, $locale);
        }

        return \sprintf(
            '<fg=cyan>%s</> → %s',
            $this->label($moveset->fast, $species, $locale),
            implode(' / ', $charged),
        );
    }

    public function holdsAnEliteMove(Moveset $moveset, Species $species): bool
    {
        foreach ($moveset->all() as $move) {
            if ($species->isElite($move)) {
                return true;
            }
        }

        return false;
    }

    private function label(MoveId $move, Species $species, ?string $locale): string
    {
        $name = $this->moves->name($move, $locale);

        return $species->isElite($move) ? \sprintf('<fg=yellow>%s%s</>', $name, self::ELITE_MARK) : $name;
    }
}
