<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure;

use App\Domain\Pokemon\Model\BaseStats;
use App\Domain\Pokemon\Model\DexNumber;
use App\Domain\Pokemon\Model\Species;
use App\Domain\Pokemon\Model\SpeciesId;
use App\Infrastructure\Catalog\NameNormalizer;
use App\Infrastructure\Naming\InputLocale;
use App\Infrastructure\Naming\SpeciesNameResolver;
use App\Tests\Fake\FixtureTranslator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class InputLocaleTest extends TestCase
{
    private InputLocale $locale;

    #[\Override]
    protected function setUp(): void
    {
        $this->locale = new InputLocale(
            new SpeciesNameResolver(FixtureTranslator::create('en')),
            new NameNormalizer(),
            ['fr', 'en'],
            'en',
        );
    }

    #[DataProvider('frenchInputs')]
    public function testAskingInFrenchAnswersInFrench(string $identifier): void
    {
        self::assertSame('fr', $this->locale->detect($identifier, $this->gengar()));
    }

    /** @return iterable<string, array{string}> */
    public static function frenchInputs(): iterable
    {
        yield 'plain' => ['Ectoplasma'];
        yield 'lowercase' => ['ectoplasma'];
        yield 'padded' => ['  Ectoplasma '];
    }

    #[DataProvider('englishInputs')]
    public function testAnythingElseKeepsTheDefault(string $identifier): void
    {
        self::assertSame('en', $this->locale->detect($identifier, $this->gengar()));
    }

    /** @return iterable<string, array{string}> */
    public static function englishInputs(): iterable
    {
        yield 'English name' => ['Gengar'];
        yield 'dex number' => ['94'];
        yield 'species id' => ['gengar'];
        yield 'empty' => [''];
        yield 'unknown' => ['Pikachouette'];
    }

    /**
     * 165 of the 1025 species are spelled the same in both languages. Guessing a
     * language from such a name would be a coin toss, so the default stands.
     */
    public function testANameSharedByBothLanguagesSettlesOnTheDefault(): void
    {
        $nidoran = new Species(
            new SpeciesId('nidoran_female'),
            new DexNumber(29),
            new BaseStats(86, 89, 146),
            shadowEligible: true,
        );

        self::assertSame('en', $this->locale->detect('Nidoran♀', $nidoran));
    }

    /**
     * A flagged form is asked for as "ectoplasma --mega", so the language must still be
     * read from the bare name rather than from a form suffix that is never typed.
     */
    public function testAFlaggedFormIsDetectedFromTheBareName(): void
    {
        $mega = new Species(
            new SpeciesId('gengar_mega'),
            new DexNumber(94),
            new BaseStats(349, 199, 155),
            shadowEligible: false,
            forms: ['mega'],
        );

        self::assertSame('fr', $this->locale->detect('Ectoplasma', $mega));
        self::assertSame('en', $this->locale->detect('Gengar', $mega));
    }

    private function gengar(): Species
    {
        return new Species(
            new SpeciesId('gengar'),
            new DexNumber(94),
            new BaseStats(261, 149, 155),
            shadowEligible: true,
        );
    }
}
