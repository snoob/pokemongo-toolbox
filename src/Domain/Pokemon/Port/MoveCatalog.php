<?php

declare(strict_types=1);

namespace App\Domain\Pokemon\Port;

use App\Domain\Pokemon\Model\Move;
use App\Domain\Pokemon\Model\MoveId;

interface MoveCatalog
{
    public function find(MoveId $id): ?Move;
}
