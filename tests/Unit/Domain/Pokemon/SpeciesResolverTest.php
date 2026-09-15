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
    public function testTheMegaFlagSwitchesToTheMegaForm(): void
    {
        $species = $this->resolver()->resolve('gengar', mega: true);

        self::assertSame('gengar_mega', $species->id->value);
    }

    public function testWithoutTheFlagThePlainFormStands(): void
    {
        self::assertSame('gengar', $this->resolver()->resolve('gengar')->id->value);
    }

    /**
     * Charizard, Raichu and Mewtwo have two megas each; the flag must not pick one.
     */
    public function testASpeciesWithTwoMegasIsReportedAsAmbiguous(): void
    {
        $catalog = $this->charizardCatalog();

        try {
            new SpeciesResolver($catalog)->resolve('charizard', mega: true);
            self::fail('Two megas cannot be narrowed to one.');
        } catch (AmbiguousSpecies $e) {
            self::assertCount(2, $e->candidates);
        }
    }

    /**
     * The X/Y pair is the only case a flag cannot resolve on its own, and the species id
     * is an internal detail the user should not have to know.
     */
    public function testAVariantPicksBetweenTheTwoMegas(): void
    {
        $resolver = new SpeciesResolver($this->charizardCatalog());

        self::assertSame('charizard_mega_x', $resolver->resolve('charizard', mega: true, megaVariant: 'x')->id->value);
        self::assertSame('charizard_mega_y', $resolver->resolve('charizard', mega: true, megaVariant: 'y')->id->value);
    }

    public function testAVariantThatTheSpeciesDoesNotHaveIsRejected(): void
    {
        $this->expectException(SpeciesNotFound::class);

        $this->resolver()->resolve('gengar', mega: true, megaVariant: 'x');
    }

    private function charizardCatalog(): InMemorySpeciesCatalog
    {
        return new InMemorySpeciesCatalog([
            $this->species('charizard', 6),
            $this->species('charizard_mega_x', 6, ['mega_x']),
            $this->species('charizard_mega_y', 6, ['mega_y']),
        ]);
    }

    public function testASpeciesWithoutAMegaSaysSo(): void
    {
        $catalog = new InMemorySpeciesCatalog([$this->species('tinkaton', 959)]);

        $this->expectException(SpeciesNotFound::class);

        new SpeciesResolver($catalog)->resolve('tinkaton', mega: true);
    }

    public function testTheShadowFlagSwitchesToTheShadowForm(): void
    {
        self::assertSame('gengar_shadow', $this->resolver()->resolve('gengar', shadow: true)->id->value);
    }

    /**
     * No form is both mega and shadow in the game, so the two flags cannot combine;
     * asking for both resolves to the mega rather than failing.
     */
    public function testAskingForBothFormsSettlesOnTheMega(): void
    {
        $species = $this->resolver()->resolve('gengar', shadow: true, mega: true);

        self::assertSame('gengar_mega', $species->id->value);
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
