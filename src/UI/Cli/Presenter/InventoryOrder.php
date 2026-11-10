<?php

declare(strict_types=1);

namespace App\UI\Cli\Presenter;

use App\Application\Inventory\ListInventory\InventoryLine;
use App\Application\Inventory\ListInventory\ListInventoryResult;
use App\Infrastructure\Catalog\NameNormalizer;
use App\Infrastructure\Naming\SpeciesNameResolver;

/**
 * The inventory in the order it is shown: by rank, as the handler gives it, then by name
 * where the ranks tie. The name is the one displayed, in the language shown, so this tie
 * is broken here rather than in the handler; accents and case do not count.
 */
final readonly class InventoryOrder
{
    public function __construct(
        private SpeciesNameResolver $names,
        private NameNormalizer $normalizer,
    ) {}

    public function sorted(ListInventoryResult $result, ?string $locale): ListInventoryResult
    {
        $lines = $result->lines;

        usort(
            $lines,
            fn(InventoryLine $a, InventoryLine $b): int => (
                [...$a->rankOrder(), $this->name($a, $locale), $a->entry->id] <=> [
                    ...$b->rankOrder(),
                    $this->name($b, $locale),
                    $b->entry->id,
                ]
            ),
        );

        return new ListInventoryResult($lines, $result->account, $result->species, $result->guessedFrom);
    }

    private function name(InventoryLine $line, ?string $locale): string
    {
        return $this->normalizer->normalize(
            null === $line->species ? $line->entry->species->value : $this->names->name($line->species, $locale),
        );
    }
}
