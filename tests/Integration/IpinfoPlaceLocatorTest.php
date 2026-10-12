<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Domain\Weather\Exception\PlaceNotFound;
use App\Infrastructure\Ipinfo\IpinfoPlaceLocator;
use App\Tests\Fake\RecordingJsonFetcher;
use PHPUnit\Framework\TestCase;

final class IpinfoPlaceLocatorTest extends TestCase
{
    public function testTheGuessCarriesATownToCheckAndItsCoordinates(): void
    {
        $place = new IpinfoPlaceLocator(new RecordingJsonFetcher([
            'city' => 'Ponthierry',
            'region' => 'Île-de-France',
            'country' => 'FR',
            'loc' => '48.5337,2.5442',
        ]))->here();

        self::assertSame('Ponthierry, FR', $place->name);
        self::assertSame(48.5337, $place->latitude);
        self::assertSame(2.5442, $place->longitude);
    }

    public function testAnAnswerWithoutCoordinatesIsNotAPlace(): void
    {
        $this->expectException(PlaceNotFound::class);

        new IpinfoPlaceLocator(new RecordingJsonFetcher(['ip' => '10.0.0.1', 'bogon' => true]))->here();
    }

    public function testAnUnreachableServiceSuggestsNamingThePlace(): void
    {
        try {
            new IpinfoPlaceLocator(new RecordingJsonFetcher(fails: true))->here();
            self::fail('an unreachable service cannot produce a place');
        } catch (PlaceNotFound $e) {
            self::assertStringContainsString('--at', $e->getMessage());
        }
    }
}
