<?php

declare(strict_types=1);

namespace App\Domain\Pokemon\Model;

final readonly class DexNumber
{
    public function __construct(
        public int $value,
    ) {
        if ($value < 1) {
            throw new \InvalidArgumentException(\sprintf('A Pokédex number must be 1 or greater, got %d.', $value));
        }
    }
}
