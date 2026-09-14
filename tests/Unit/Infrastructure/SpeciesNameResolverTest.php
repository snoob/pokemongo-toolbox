<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure;

use App\Domain\Pokemon\Model\BaseStats;
use App\Domain\Pokemon\Model\DexNumber;
use App\Domain\Pokemon\Model\Species;
use App\Domain\Pokemon\Model\SpeciesId;
use App\Infrastructure\Naming\SpeciesNameResolver;
use App\Tests\Fake\FixtureTranslator;
use PHPUnit\Framework\TestCase;

final class SpeciesNameResolverTest extends TestCase
{
    private SpeciesNameResolver $resolver;

    #[\Override]
    protected function setUp(): void
    {
        $this->resolver = new SpeciesNameResolver(FixtureTranslator::create());
    }

    public function testADefaultFormIsJustItsName(): void
    {
        self::assertSame('Ectoplasma', $this->resolver->name($this->gengar()));
        self::assertSame('Gengar', $this->resolver->name($this->gengar(), 'en'));
    }

    public function testAFormAppendsItsTranslatedSegment(): void
    {
        $shadow = $this->gengar(['shadow']);

        self::assertSame('Ectoplasma (Obscur)', $this->resolver->name($shadow));
        self::assertSame('Gengar (Shadow)', $this->resolver->name($shadow, 'en'));
    }

    public function testSegmentsAreAppendedInOrder(): void
    {
        $species = $this->gengar(['altered', 'shadow']);

        self::assertSame('Ectoplasma (Alternative) (Obscur)', $this->resolver->name($species));
    }

    public function testAnUntranslatedSegmentFallsBackRatherThanVanishing(): void
    {
        // "paldean" is absent from the fixture catalogues; the key must survive so the
        // form stays distinguishable instead of collapsing onto the default form.
        self::assertSame('Ectoplasma (paldean)', $this->resolver->name($this->gengar(['paldean'])));
    }

    /**
     * @param list<string> $forms
     */
    private function gengar(array $forms = []): Species
    {
        return new Species(
            new SpeciesId('gengar'),
            new DexNumber(94),
            new BaseStats(261, 149, 155),
            shadowEligible: true,
            forms: $forms,
        );
    }
}
