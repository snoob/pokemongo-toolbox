<?php

declare(strict_types=1);

namespace App\Infrastructure\Json;

/**
 * Infrastructure-level port: hands back a decoded JSON document, whatever the
 * transport. Adapters depend on this, never on an HTTP client directly, so caching
 * can be layered in as a decorator.
 */
interface JsonFetcher
{
    /**
     * @return array<array-key, mixed>
     *
     * @throws JsonFetchFailed
     */
    public function fetch(string $url): array;
}
