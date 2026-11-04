<?php

declare(strict_types=1);

namespace App\Application\Inventory\AddToInventory;

use App\Domain\Pokemon\Exception\InvalidIv;
use App\Domain\Pokemon\Model\IvPattern;
use App\Domain\Pvp\Model\Cp;
use App\Domain\Pvp\Model\League;

final readonly class AddToInventoryCommand
{
    /**
     * @param non-empty-list<League> $leagues one entry is kept per league
     * @param ?Cp                    $cp      the CP the game shows: it checks the IVs, and
     *                                        settles those left unknown
     */
    public function __construct(
        public string $identifier,
        public IvPattern $iv,
        public array $leagues,
        public bool $shadow = false,
        public ?Cp $cp = null,
    ) {
        if (null === $cp && !$iv->isComplete()) {
            throw InvalidIv::unknownWithoutCp($iv);
        }
    }
}
