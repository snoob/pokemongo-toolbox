<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Infrastructure\Json\CachedJsonFetcher;
use App\Infrastructure\Json\JsonFetchFailed;
use App\Tests\Fake\RecordingJsonFetcher;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class CachedJsonFetcherTest extends TestCase
{
    private const int TTL = 3600;

    private string $directory;

    #[\Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/pogo-cache-' . bin2hex(random_bytes(6));
    }

    #[\Override]
    protected function tearDown(): void
    {
        $files = glob($this->directory . '/*');

        foreach (false === $files ? [] : $files as $file) {
            unlink($file);
        }

        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
    }

    public function testASecondReadWithinTheTtlDoesNotHitTheSource(): void
    {
        $inner = new RecordingJsonFetcher(['hello' => 'world']);
        $fetcher = new CachedJsonFetcher($inner, new MockClock('2026-01-01 00:00:00'), $this->directory, self::TTL);

        self::assertSame(['hello' => 'world'], $fetcher->fetch('https://example.test/a.json'));
        self::assertSame(['hello' => 'world'], $fetcher->fetch('https://example.test/a.json'));
        self::assertSame(1, $inner->calls);
    }

    public function testOnceTheTtlHasPassedTheSourceIsReadAgain(): void
    {
        $inner = new RecordingJsonFetcher(['hello' => 'world']);
        $clock = new MockClock('2026-01-01 00:00:00');
        $fetcher = new CachedJsonFetcher($inner, $clock, $this->directory, self::TTL);

        $fetcher->fetch('https://example.test/a.json');
        $clock->sleep(self::TTL + 1);
        $fetcher->fetch('https://example.test/a.json');

        self::assertSame(2, $inner->calls);
    }

    public function testDistinctUrlsAreCachedSeparately(): void
    {
        $inner = new RecordingJsonFetcher(['hello' => 'world']);
        $fetcher = new CachedJsonFetcher($inner, new MockClock('2026-01-01 00:00:00'), $this->directory, self::TTL);

        $fetcher->fetch('https://example.test/a.json');
        $fetcher->fetch('https://example.test/b.json');

        self::assertSame(2, $inner->calls);
    }

    public function testAStaleCopyIsServedWhenTheSourceIsUnreachable(): void
    {
        $inner = new RecordingJsonFetcher(['hello' => 'world']);
        $clock = new MockClock('2026-01-01 00:00:00');
        $fetcher = new CachedJsonFetcher($inner, $clock, $this->directory, self::TTL);

        $fetcher->fetch('https://example.test/a.json');
        $clock->sleep(self::TTL + 1);
        $inner->startFailing();

        self::assertSame(['hello' => 'world'], $fetcher->fetch('https://example.test/a.json'));
    }

    public function testWithoutAnyCachedCopyTheFailureSurfaces(): void
    {
        $fetcher = new CachedJsonFetcher(
            new RecordingJsonFetcher(fails: true),
            new MockClock('2026-01-01 00:00:00'),
            $this->directory,
            self::TTL,
        );

        $this->expectException(JsonFetchFailed::class);

        $fetcher->fetch('https://example.test/a.json');
    }
}
