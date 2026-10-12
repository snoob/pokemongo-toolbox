<?php

declare(strict_types=1);

namespace App\Domain\Weather\Port;

use App\Domain\Weather\Exception\PlaceNotFound;
use App\Domain\Weather\Model\Place;

interface PlaceLocator
{
    /**
     * Where the player most likely is right now. A guess, accurate to the town at best,
     * which is why the answer carries a name the player can check.
     *
     * @throws PlaceNotFound
     */
    public function here(): Place;
}
