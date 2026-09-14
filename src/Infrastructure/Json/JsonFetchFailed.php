<?php

declare(strict_types=1);

namespace App\Infrastructure\Json;

final class JsonFetchFailed extends \RuntimeException
{
    public static function for(string $url, string $reason, ?\Throwable $previous = null): self
    {
        return new self(\sprintf('Could not read JSON from %s: %s', $url, $reason), previous: $previous);
    }
}
