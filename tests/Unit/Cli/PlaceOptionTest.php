<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cli;

use App\UI\Cli\Input\PlaceOption;
use PHPUnit\Framework\TestCase;

final class PlaceOptionTest extends TestCase
{
    public function testAutoGuessesFromTheIpAddress(): void
    {
        self::assertNull(PlaceOption::parse('auto'));
        self::assertNull(PlaceOption::parse(' Auto '));
        self::assertNull(PlaceOption::parse(null));
        self::assertNull(PlaceOption::parse(''));
    }

    public function testAPlaceIsLookedUpAsTyped(): void
    {
        self::assertSame('Villemomble, France', PlaceOption::parse(' Villemomble, France '));
        self::assertSame('Autun, France', PlaceOption::parse('Autun, France'), 'only "auto" itself is the guess');
    }
}
