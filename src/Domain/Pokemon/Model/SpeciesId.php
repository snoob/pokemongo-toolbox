<?php

declare(strict_types=1);

namespace App\Domain\Pokemon\Model;

/**
 * Natural key of a Pokémon GO form, e.g. "alakazam", "alakazam_shadow", "giratina_altered".
 * One species may hold several forms sharing a single Pokédex number.
 */
final readonly class SpeciesId implements \Stringable
{
    public function __construct(
        public string $value,
    ) {
        if ('' === trim($value)) {
            throw new \InvalidArgumentException('A species id cannot be empty.');
        }
    }

    public function shadow(): self
    {
        return str_ends_with($this->value, '_shadow') ? $this : new self($this->value . '_shadow');
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
