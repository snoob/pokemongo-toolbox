<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Exception;

final class EntryNotFound extends \DomainException
{
    public static function withId(int $id): self
    {
        return new self(\sprintf('No inventory entry #%d; "inventory:list" shows the numbers.', $id));
    }
}
