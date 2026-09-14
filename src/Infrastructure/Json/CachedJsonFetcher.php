<?php

declare(strict_types=1);

namespace App\Infrastructure\Json;

use Psr\Clock\ClockInterface;

/**
 * Disk cache as a decorator: the business code never knows whether a document came
 * from the network or from var/. Lets us test the maths without a cache, and the
 * cache without a network.
 */
final readonly class CachedJsonFetcher implements JsonFetcher
{
    public function __construct(
        private JsonFetcher $inner,
        private ClockInterface $clock,
        private string $cacheDirectory,
        private int $ttlSeconds,
    ) {}

    #[\Override]
    public function fetch(string $url): array
    {
        $file = \sprintf('%s/%s.json', $this->cacheDirectory, hash('xxh128', $url));
        $now = $this->clock->now()->getTimestamp();

        $cached = $this->read($file);

        if (null !== $cached && ($now - $cached['fetchedAt']) < $this->ttlSeconds) {
            return $cached['payload'];
        }

        try {
            $payload = $this->inner->fetch($url);
        } catch (JsonFetchFailed $e) {
            // A stale copy beats no answer at all when the source is unreachable.
            if (null !== $cached) {
                return $cached['payload'];
            }

            throw $e;
        }

        $this->write($file, ['fetchedAt' => $now, 'payload' => $payload]);

        return $payload;
    }

    /**
     * @return array{fetchedAt: int, payload: array<array-key, mixed>}|null
     */
    private function read(string $file): ?array
    {
        if (!is_file($file)) {
            return null;
        }

        $raw = file_get_contents($file);

        if (false === $raw) {
            return null;
        }

        try {
            $decoded = JsonValue::asArray(json_decode($raw, true, flags: \JSON_THROW_ON_ERROR));
        } catch (\JsonException) {
            return null;
        }

        $fetchedAt = JsonPath::intAt($decoded, 'fetchedAt');
        $payload = JsonPath::arrayAt($decoded, 'payload');

        if (null === $fetchedAt || null === $payload) {
            return null;
        }

        return ['fetchedAt' => $fetchedAt, 'payload' => $payload];
    }

    /**
     * @param array{fetchedAt: int, payload: array<array-key, mixed>} $entry
     */
    private function write(string $file, array $entry): void
    {
        if (
            !is_dir($this->cacheDirectory)
            && !mkdir($this->cacheDirectory, 0o775, true)
            && !is_dir($this->cacheDirectory)
        ) {
            return;
        }

        file_put_contents($file, json_encode($entry, \JSON_THROW_ON_ERROR));
    }
}
