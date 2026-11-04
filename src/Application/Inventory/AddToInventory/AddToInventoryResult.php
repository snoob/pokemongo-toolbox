<?php

declare(strict_types=1);

namespace App\Application\Inventory\AddToInventory;

use App\Domain\Pokemon\Model\IvSpread;
use App\Domain\Pokemon\Model\Species;
use App\Domain\Pvp\Model\IvDeduction;

/**
 * `guessedFrom` holds the identifier as typed when the nearest name was taken instead;
 * `deduction` is set when a CP was given.
 */
final readonly class AddToInventoryResult
{
    /**
     * @param non-empty-list<AddedEntry> $added
     */
    public function __construct(
        public Species $species,
        public IvSpread $iv,
        /** @var non-empty-list<AddedEntry> */
        public array $added,
        public ?string $guessedFrom = null,
        public ?IvDeduction $deduction = null,
    ) {}
}
