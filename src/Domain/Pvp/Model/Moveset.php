<?php

declare(strict_types=1);

namespace App\Domain\Pvp\Model;

use App\Domain\Pokemon\Model\MoveId;

/**
 * The moveset a ranking source recommends for a species in a league: one fast move and
 * the charged moves to pair with it.
 */
final readonly class Moveset
{
    /**
     * @param non-empty-list<MoveId> $charged
     */
    public function __construct(
        public MoveId $fast,
        public array $charged,
    ) {}

    /**
     * @return non-empty-list<MoveId> the fast move first, as the game orders them
     */
    public function all(): array
    {
        return [$this->fast, ...$this->charged];
    }
}
