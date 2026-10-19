<?php

declare(strict_types=1);

namespace App\Infrastructure\Catalog;

/**
 * Keeps whatever lies at the smallest distance offered so far: a closer offer replaces
 * the lot, an equal one joins it, a farther one — or one too far to count — is dropped.
 *
 * @template T
 */
final class NearestCandidates
{
    private ?int $distance = null;

    /** @var list<T> */
    private array $items = [];

    /**
     * @param list<T> $items
     */
    public function offer(?int $distance, array $items): void
    {
        if (null === $distance || null !== $this->distance && $distance > $this->distance) {
            return;
        }

        if (null === $this->distance || $distance < $this->distance) {
            $this->distance = $distance;
            $this->items = [];
        }

        $this->items = [...$this->items, ...$items];
    }

    /**
     * @return list<T>
     */
    public function items(): array
    {
        return $this->items;
    }
}
