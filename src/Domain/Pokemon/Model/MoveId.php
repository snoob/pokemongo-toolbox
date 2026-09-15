<?php

declare(strict_types=1);

namespace App\Domain\Pokemon\Model;

/**
 * A move as the game names it, e.g. "SHADOW_CLAW" or "PSYBEAM_PLUS".
 */
final readonly class MoveId implements \Stringable
{
    public function __construct(
        public string $value,
    ) {
        if ('' === trim($value)) {
            throw new \InvalidArgumentException('A move id cannot be empty.');
        }
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->value;
    }
}
