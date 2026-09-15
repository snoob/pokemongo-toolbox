<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use App\Domain\Pokemon\Model\Move;
use App\Domain\Pokemon\Model\MoveId;
use App\Domain\Pokemon\Port\MoveCatalog;

final readonly class InMemoryMoveCatalog implements MoveCatalog
{
    /** @var array<string, Move> */
    private array $moves;

    public function __construct(Move ...$moves)
    {
        $indexed = [];

        foreach ($moves as $move) {
            $indexed[$move->id->value] = $move;
        }

        $this->moves = $indexed;
    }

    #[\Override]
    public function find(MoveId $id): ?Move
    {
        return $this->moves[$id->value] ?? null;
    }
}
