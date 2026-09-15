<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Domain\Pokemon\Model\SpeciesId;
use App\Domain\Pvp\Model\League;
use App\Infrastructure\Json\HttpJsonFetcher;
use App\Infrastructure\Pvpoke\PvpokeRankingCatalog;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Runs against a frozen extract: a ranking update upstream must never turn this red.
 */
final class PvpokeRankingCatalogTest extends TestCase
{
    private int $requests = 0;

    /** @var list<string> */
    private array $urls = [];

    #[\Override]
    protected function setUp(): void
    {
        $this->requests = 0;
        $this->urls = [];
    }

    public function testItTurnsFilePositionIntoARank(): void
    {
        $rank = $this->catalog()->rankOf(new SpeciesId('gengar'), League::Great);

        self::assertNotNull($rank);
        self::assertSame(3, $rank->position, 'the rank is the position in the file, counted from 1');
        self::assertSame(4, $rank->total);
        self::assertSame(73.0, $rank->score);
        self::assertSame(League::Great, $rank->league);
    }

    public function testItReadsTheRecommendedMoveset(): void
    {
        $moveset = $this->catalog()->rankOf(new SpeciesId('gengar'), League::Great)?->moveset;

        self::assertNotNull($moveset);
        self::assertSame('SHADOW_CLAW', (string) $moveset->fast, 'the source lists the fast move first');
        self::assertSame(['SHADOW_PUNCH', 'DARK_PULSE'], array_map(strval(...), $moveset->charged));
    }

    public function testAMegaCarryingAThirdChargedMoveIsReadAsSuch(): void
    {
        $payload = json_encode(
            [[
                'speciesId' => 'malamar_mega',
                'score' => 80,
                'moveset' => ['PSYWAVE', 'FOUL_PLAY', 'SUPER_POWER', 'PSYBEAM_PLUS'],
            ]],
            \JSON_THROW_ON_ERROR,
        );

        $moveset = $this->catalog($payload)->rankOf(new SpeciesId('malamar_mega'), League::Great)?->moveset;

        self::assertNotNull($moveset);
        self::assertSame(
            ['FOUL_PLAY', 'SUPER_POWER', 'PSYBEAM_PLUS'],
            array_map(strval(...), $moveset->charged),
            'super megas carry a third charged move, and it is the mega-only one',
        );
    }

    public function testAnUnrankedSpeciesReturnsNothing(): void
    {
        self::assertNull($this->catalog()->rankOf(new SpeciesId('mewtwo'), League::Great));
    }

    public function testShadowFormsAreRankedInTheirOwnRight(): void
    {
        $rank = $this->catalog()->rankOf(new SpeciesId('ninetales_shadow'), League::Great);

        self::assertSame(2, $rank?->position);
    }

    public function testEachLeagueIsFetchedOnceAndThenReused(): void
    {
        $catalog = $this->catalog();

        $catalog->rankOf(new SpeciesId('gengar'), League::Great);
        $catalog->rankOf(new SpeciesId('alakazam'), League::Great);
        $catalog->rankOf(new SpeciesId('tinkaton'), League::Great);

        self::assertSame(1, $this->requests, 'three lookups in one league must not mean three downloads');
    }

    public function testEachLeagueReadsItsOwnFile(): void
    {
        $catalog = $this->catalog('[]');

        foreach (League::all() as $league) {
            $catalog->rankOf(new SpeciesId('gengar'), $league);
        }

        // The standard leagues live under the "all" cup, the Mega Editions under "mega".
        self::assertSame(
            [
                'all/overall/rankings-1500.json',
                'all/overall/rankings-2500.json',
                'all/overall/rankings-10000.json',
                'mega/overall/rankings-1500.json',
                'mega/overall/rankings-2500.json',
                'mega/overall/rankings-10000.json',
            ],
            array_map(static fn(string $url): string => implode('/', \array_slice(
                explode('/', $url),
                -3,
            )), $this->urls),
        );
    }

    private function catalog(?string $payload = null): PvpokeRankingCatalog
    {
        $body = $payload ?? (string) file_get_contents(__DIR__ . '/../Fixtures/rankings-1500.json');

        $client = new MockHttpClient(function (string $_method, string $url) use ($body): MockResponse {
            ++$this->requests;
            $this->urls[] = $url;

            return new MockResponse($body);
        });

        return new PvpokeRankingCatalog(new HttpJsonFetcher($client));
    }
}
