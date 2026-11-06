<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cli;

use App\UI\Cli\Settings\SettingValues;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SettingValuesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function valid(): iterable
    {
        yield 'a floor by its source' => ['floor', ['best']];
        yield 'a floor by its number' => ['floor', ['5']];
        yield 'several leagues' => ['league', ['great', 'ultra', 'master']];
        yield 'an account' => ['account', ['Snoob2555']];
        yield 'a format' => ['format', ['json']];
        yield 'a locale' => ['locale', ['fr']];
        yield 'free text' => ['at', ['Villemomble, France']];
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function invalid(): iterable
    {
        yield 'an unknown floor' => ['floor', ['bestie']];
        yield 'a floor above 15' => ['floor', ['16']];
        yield 'one unknown league among others' => ['league', ['great', 'hyper']];
        yield 'an account that cannot name a file' => ['account', ['../etc']];
        yield 'an unknown format' => ['format', ['yaml']];
        yield 'an unknown locale' => ['locale', ['de']];
        yield 'a CP under 10' => ['cp', ['3']];
    }

    /**
     * @param list<string> $values
     */
    #[DataProvider('valid')]
    public function testAValueTheCommandWouldReadIsAccepted(string $option, array $values): void
    {
        SettingValues::check($option, $values);

        $this->addToAssertionCount(1);
    }

    /**
     * @param list<string> $values
     */
    #[DataProvider('invalid')]
    public function testAValueTheCommandWouldRefuseIsRefused(string $option, array $values): void
    {
        $this->expectException(\InvalidArgumentException::class);

        SettingValues::check($option, $values);
    }
}
