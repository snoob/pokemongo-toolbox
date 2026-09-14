<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use App\Infrastructure\Json\JsonFetcher;
use App\Infrastructure\Json\JsonFetchFailed;

final class RecordingJsonFetcher implements JsonFetcher
{
    public int $calls = 0;

    /**
     * @param array<array-key, mixed> $payload
     */
    public function __construct(
        private array $payload = [],
        private bool $fails = false,
    ) {}

    #[\Override]
    public function fetch(string $url): array
    {
        ++$this->calls;

        if ($this->fails) {
            throw JsonFetchFailed::for($url, 'unreachable');
        }

        return $this->payload;
    }

    public function startFailing(): void
    {
        $this->fails = true;
    }
}
