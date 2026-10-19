<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Pokemon;

use App\Domain\Pokemon\Exception\AmbiguousSpecies;
use App\Domain\Pokemon\Exception\SpeciesNotFound;
use App\Domain\Pokemon\Model\BaseStats;
use App\Domain\Pokemon\Model\DexNumber;
use App\Domain\Pokemon\Model\Species;
use App\Domain\Pokemon\Model\SpeciesId;
use App\Domain\Pokemon\Service\SpeciesResolver;
use App\Tests\Fake\InMemorySpeciesCatalog;
use PHPUnit\Framework\TestCase;

final class SpeciesResolverTest extends TestCase
{
    public function testWithoutTheFlagThePlainFormStands(): void
    {
        self::assertSame('gengar', $this->resolver()->resolve('gengar')->species->id->value);
    }

    public function testTheShadowFlagSwitchesToTheShadowForm(): void
    {
        self::assertSame('gengar_shadow', $this->resolver()->resolveShadow('gengar')->species->id->value);
    }

    public function testTheShadowFlagFallsBackWhenNoShadowFormExists(): void
    {
        $resolver = new SpeciesResolver(new InMemorySpeciesCatalog([$this->species('tinkaton', 959)]));

        self::assertSame('tinkaton', $resolver->resolveShadow('tinkaton')->species->id->value);
    }

    public function testAnExactNameIsNotAGuess(): void
    {
        self::assertFalse($this->resolver()->resolve('Ectoplasma')->guessed);
    }

    public function testATypoIsTakenForTheNearestNameAndSaysSo(): void
    {
        $match = $this->resolver()->resolve('Ectoplsama');

        self::assertSame('gengar', $match->species->id->value);
        self::assertTrue($match->guessed);
    }

    public function testTheShadowFlagKeepsTheGuess(): void
    {
        $match = $this->resolver()->resolveShadow('Ectoplsama');

        self::assertSame('gengar_shadow', $match->species->id->value);
        self::assertTrue($match->guessed);
    }

    public function testTwoNamesEquallyCloseAreOfferedRatherThanPicked(): void
    {
        $resolver = new SpeciesResolver(
            new InMemorySpeciesCatalog([
                $this->species('nidoran_female', 29),
                $this->species('nidoran_male', 32),
            ], [
                'nidoran_female' => ['Nidoran F'],
                'nidoran_male' => ['Nidoran M'],
            ]),
        );

        $this->expectException(AmbiguousSpecies::class);

        $resolver->resolve('Nidoran X');
    }

    public function testAnUnknownNameIsRejected(): void
    {
        $this->expectException(SpeciesNotFound::class);

        $this->resolver()->resolve('Pikachouette');
    }

    public function testAnAmbiguousIdentifierListsTheCandidatesInsteadOfGuessing(): void
    {
        $resolver = new SpeciesResolver(new InMemorySpeciesCatalog([
            $this->species('giratina_altered', 487, ['altered']),
            $this->species('giratina_origin', 487, ['origin']),
        ]));

        try {
            $resolver->resolve('487');
            self::fail('An ambiguous identifier must not resolve silently.');
        } catch (AmbiguousSpecies $e) {
            self::assertCount(2, $e->candidates);
            self::assertSame('487', $e->identifier);
        }
    }

    private function resolver(): SpeciesResolver
    {
        return new SpeciesResolver(
            new InMemorySpeciesCatalog([
                $this->species('gengar', 94),
                $this->species('gengar_mega', 94, ['mega']),
                $this->species('gengar_shadow', 94, ['shadow']),
            ], [
                'gengar' => ['Gengar', 'Ectoplasma'],
            ]),
        );
    }

    /**
     * @param list<string> $forms
     */
    private function species(string $id, int $dex, array $forms = []): Species
    {
        return new Species(
            new SpeciesId($id),
            new DexNumber($dex),
            new BaseStats(261, 149, 155),
            shadowEligible: true,
            forms: $forms,
        );
    }
}
