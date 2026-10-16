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
        self::assertSame('gengar', $this->resolver()->resolve('gengar')->id->value);
    }

    public function testTheShadowFlagSwitchesToTheShadowForm(): void
    {
        self::assertSame('gengar_shadow', $this->resolver()->resolveShadow('gengar')->id->value);
    }

    public function testTheShadowFlagFallsBackWhenNoShadowFormExists(): void
    {
        $resolver = new SpeciesResolver(new InMemorySpeciesCatalog([$this->species('tinkaton', 959)]));

        self::assertSame('tinkaton', $resolver->resolveShadow('tinkaton')->id->value);
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
        return new SpeciesResolver(new InMemorySpeciesCatalog([
            $this->species('gengar', 94),
            $this->species('gengar_mega', 94, ['mega']),
            $this->species('gengar_shadow', 94, ['shadow']),
        ]));
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
