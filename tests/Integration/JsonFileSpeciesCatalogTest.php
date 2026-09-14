<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Domain\Pokemon\Model\Species;
use App\Domain\Pokemon\Model\SpeciesId;
use App\Infrastructure\Catalog\JsonFileSpeciesCatalog;
use App\Infrastructure\Catalog\NameNormalizer;
use App\Infrastructure\Catalog\SpeciesFileReader;
use App\Infrastructure\Naming\LocalisedSpeciesNames;
use App\Infrastructure\Naming\SpeciesNameResolver;
use App\Tests\Fake\FixtureTranslator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class JsonFileSpeciesCatalogTest extends TestCase
{
    private JsonFileSpeciesCatalog $catalog;

    #[\Override]
    protected function setUp(): void
    {
        $this->catalog = $this->catalogFor(__DIR__ . '/../Fixtures/species.json');
    }

    #[DataProvider('identifiersForGengar')]
    public function testTheThreeWaysOfNamingAPokemonResolveToTheSameSpecies(string $identifier): void
    {
        self::assertSame(['gengar'], $this->idsFor($identifier));
    }

    /** @return iterable<string, array{string}> */
    public static function identifiersForGengar(): iterable
    {
        yield 'French name' => ['Ectoplasma'];
        yield 'English name' => ['Gengar'];
        yield 'dex number' => ['94'];
        yield 'lowercase' => ['ectoplasma'];
        yield 'uppercase' => ['GENGAR'];
        yield 'padded' => ['  Ectoplasma  '];
        yield 'species id' => ['gengar'];
    }

    public function testAlternateFormsDoNotDrownTheDefaultForm(): void
    {
        // The fixture holds Gengar, Mega Gengar and Shadow Gengar under dex 94.
        self::assertSame(['gengar'], $this->idsFor('94'));
    }

    #[DataProvider('formIdentifiers')]
    public function testAFormIsReachedByItsNameInEitherLanguage(string $identifier, string $expected): void
    {
        self::assertSame([$expected], $this->idsFor($identifier));
    }

    /** @return iterable<string, array{string, string}> */
    public static function formIdentifiers(): iterable
    {
        yield 'English form' => ['Gengar (Mega)', 'gengar_mega'];
        yield 'French form' => ['Ectoplasma (Méga)', 'gengar_mega'];
        yield 'French shadow' => ['Ectoplasma (Obscur)', 'gengar_shadow'];
        yield 'English shadow' => ['Gengar (Shadow)', 'gengar_shadow'];
    }

    public function testAnIdentifierCoveringSeveralFormsReturnsThemAll(): void
    {
        $found = $this->idsFor('487');

        self::assertCount(2, $found, 'Giratina has no default form: both must be offered.');
    }

    public function testGenderSignsAreKeptApart(): void
    {
        self::assertSame(['nidoran_female'], $this->idsFor('Nidoran♀'));
        self::assertSame(['nidoran_male'], $this->idsFor('Nidoran♂'));
        self::assertCount(2, $this->idsFor('nidoran'), 'A bare "nidoran" is genuinely ambiguous.');
    }

    public function testAnUnknownNameYieldsNothing(): void
    {
        self::assertSame([], $this->idsFor('Pikachouette'));
        self::assertSame([], $this->idsFor(''));
    }

    public function testItLooksUpAFormById(): void
    {
        self::assertNotNull($this->catalog->find(new SpeciesId('gengar_shadow')));
        self::assertNull($this->catalog->find(new SpeciesId('tinkaton_shadow')));
    }

    public function testAMissingFileSaysHowToFixIt(): void
    {
        $catalog = $this->catalogFor('/nowhere/species.json');

        try {
            $catalog->search('gengar');
            self::fail('A missing species file must not pass silently.');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('pogo:data:build', $e->getMessage());
        }
    }

    private function catalogFor(string $file): JsonFileSpeciesCatalog
    {
        return new JsonFileSpeciesCatalog(
            new SpeciesFileReader($file),
            new NameNormalizer(),
            new LocalisedSpeciesNames(new SpeciesNameResolver(FixtureTranslator::create()), ['fr', 'en']),
        );
    }

    /**
     * @return list<string>
     */
    private function idsFor(string $identifier): array
    {
        return array_map(static fn(Species $s): string => $s->id->value, $this->catalog->search($identifier));
    }
}
