<?php

declare(strict_types=1);

namespace App\Domain\Weather\Exception;

final class WeatherUnavailable extends \RuntimeException
{
    public static function because(string $reason, ?\Throwable $previous = null): self
    {
        return new self(\sprintf('The weather forecast is unavailable: %s', $reason), previous: $previous);
    }
}
