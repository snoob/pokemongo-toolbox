<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Domain\Pokemon\Model\Species;
use App\Domain\Pokemon\Model\SpeciesId;
use App\Infrastructure\Catalog\JsonFileSpeciesCatalog;
use App\Infrastructure\Catalog\NameNormalizer;
use App\Infrastructure\Catalog\SpeciesFileReader;
use App\Infrastructure\Catalog\TypoTolerance;
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

    /**
     * Mega and shadow are asked for with their own flags, so naming them resolves to
     * nothing rather than offering a second, redundant way in.
     */
    #[DataProvider('flaggedFormNames')]
    public function testNamingAFlaggedFormNoLongerResolvesIt(string $identifier): void
    {
        self::assertSame([], $this->idsFor($identifier));
    }

    /** @return iterable<string, array{string}> */
    public static function flaggedFormNames(): iterable
    {
        yield 'English mega' => ['Gengar (Mega)'];
        yield 'French mega' => ['Ectoplasma (Méga)'];
        yield 'English shadow' => ['Gengar (Shadow)'];
        yield 'French shadow' => ['Ectoplasma (Obscur)'];
    }

    /**
     * Regional forms have no flag, so their name stays the only way to reach them.
     */
    #[DataProvider('regionalFormNames')]
    public function testARegionalFormIsStillReachedByItsName(string $identifier): void
    {
        self::assertSame(['raichu_alolan'], $this->idsFor($identifier));
    }

    /** @return iterable<string, array{string}> */
    public static function regionalFormNames(): iterable
    {
        yield 'French' => ["Raichu (d'Alola)"];
        yield 'English' => ['Raichu (Alolan)'];
        yield 'accent-free' => ['raichu dalola'];
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

    #[DataProvider('typos')]
    public function testATypoIsTakenForTheNearestName(string $identifier, string $expected): void
    {
        self::assertSame([], $this->idsFor($identifier), 'a typo must not pass for an exact match');
        self::assertSame([$expected], $this->nearestIdsFor($identifier));
    }

    /** @return iterable<string, array{string, string}> */
    public static function typos(): iterable
    {
        yield 'French, one letter swapped for another' => ['Ectoplasmo', 'gengar'];
        yield 'French, two letters transposed' => ['Ectoplsama', 'gengar'];
        yield 'English, one letter missing' => ['Gengr', 'gengar'];
        yield 'English, one letter too many' => ['Gengarr', 'gengar'];
        yield 'regional form' => ['Raichu dAlolla', 'raichu_alolan'];
    }

    public function testNothingIsGuessedWhenNoNameIsCloseEnough(): void
    {
        self::assertSame([], $this->nearestIdsFor('Pikachouette'));
        self::assertSame([], $this->nearestIdsFor('Gegnr'), 'three edits in five letters is another word');
        self::assertSame([], $this->nearestIdsFor('95'), 'a dex number one digit off is another Pokémon');
        self::assertSame([], $this->nearestIdsFor(''));
    }

    public function testATypoInTheStartOfANameStillFindsIt(): void
    {
        self::assertSame(['gengar'], $this->nearestIdsFor('Ectoplsa'));
    }

    public function testAWholeNameBeatsTheStartOfALongerOne(): void
    {
        // "Raichu" is a whole name one edit away, and also the start of "Raichu (Alolan)".
        self::assertSame(['raichu'], $this->nearestIdsFor('Raichi'));
    }

    public function testNamesTiedAtTheSameDistanceAreAllOffered(): void
    {
        // Giratina has no default form: a typo in the bare name reaches both.
        self::assertCount(2, $this->nearestIdsFor('Giratima'));
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
            self::assertStringContainsString('app:data:build', $e->getMessage());
        }
    }

    private function catalogFor(string $file): JsonFileSpeciesCatalog
    {
        return new JsonFileSpeciesCatalog(
            new SpeciesFileReader($file),
            new NameNormalizer(),
            new LocalisedSpeciesNames(new SpeciesNameResolver(FixtureTranslator::create()), ['fr', 'en']),
            new TypoTolerance(),
        );
    }

    /**
     * @return list<string>
     */
    private function nearestIdsFor(string $identifier): array
    {
        return array_map(static fn(Species $s): string => $s->id->value, $this->catalog->nearest($identifier));
    }

    /**
     * @return list<string>
     */
    private function idsFor(string $identifier): array
    {
        return array_map(static fn(Species $s): string => $s->id->value, $this->catalog->search($identifier));
    }
}
