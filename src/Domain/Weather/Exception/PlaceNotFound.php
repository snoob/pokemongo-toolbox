<?php

declare(strict_types=1);

namespace App\Domain\Weather\Exception;

final class PlaceNotFound extends \RuntimeException
{
    public static function forQuery(string $query): self
    {
        return new self(\sprintf('No place matches "%s" (try "Town, Country").', $query));
    }

    public static function fromNetwork(string $reason): self
    {
        return new self(\sprintf('Could not work out where you are (%s); name a place with --at.', $reason));
    }
}
