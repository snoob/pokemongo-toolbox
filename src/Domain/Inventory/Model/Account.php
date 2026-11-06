<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Model;

/**
 * A game account the player keeps Pokémon for: each one has its own inventory, numbered
 * on its own. Trainer names ignore case in the game, so the name is kept in lower case,
 * and it is restricted to what can safely name a file.
 */
final readonly class Account implements \Stringable
{
    public const string DEFAULT = 'default';

    public string $name;

    public function __construct(string $name)
    {
        $name = strtolower(trim($name));

        if (1 !== preg_match('/^[a-z0-9][a-z0-9_-]{0,31}$/', $name)) {
            throw new \InvalidArgumentException(\sprintf(
                'Cannot use "%s" as an account name: letters, digits, "-" and "_", up to 32 characters.',
                $name,
            ));
        }

        $this->name = $name;
    }

    /**
     * The account used when none is given nor set.
     */
    public static function default(): self
    {
        return new self(self::DEFAULT);
    }

    public function equals(self $other): bool
    {
        return $this->name === $other->name;
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->name;
    }
}
