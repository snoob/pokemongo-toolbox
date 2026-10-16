<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Pokemon;

use App\Domain\Pokemon\Model\BaseStats;
use App\Domain\Pokemon\Model\DexNumber;
use App\Domain\Pokemon\Model\EvolutionPath;
use App\Domain\Pokemon\Model\Species;
use App\Domain\Pokemon\Model\SpeciesId;
use App\Domain\Pokemon\Service\EvolutionTree;
use App\Tests\Fake\InMemorySpeciesCatalog;
use PHPUnit\Framework\TestCase;

final class EvolutionTreeTest extends TestCase
{
    /**
     * Every form the asked one can become, each followed by its mega: the order a player
     * reads the family in.
     */
    public function testABaseFormLeadsToEveryEvolutionAndItsMega(): void
    {
        self::assertSame(
            [
                'ralts',
                'ralts > kirlia',
                'ralts > kirlia > gardevoir',
                'ralts > kirlia > gardevoir > gardevoir_mega',
                'ralts > kirlia > gallade',
                'ralts > kirlia > gallade > gallade_mega',
            ],
            $this->walk('ralts'),
        );
    }

    public function testAFinalFormStillLeadsToItsMega(): void
    {
        self::assertSame(['gardevoir', 'gardevoir > gardevoir_mega'], $this->walk('gardevoir'));
    }

    public function testBothMegasOfACharizardAreReached(): void
    {
        self::assertSame(
            ['charizard', 'charizard > charizard_mega_x', 'charizard > charizard_mega_y'],
            $this->walk('charizard'),
        );
    }

    public function testAFormThatEvolvesNoFurtherStandsAlone(): void
    {
        self::assertSame(['umbreon'], $this->walk('umbreon'));
    }

    /**
     * A shadow keeps its shadow when it evolves, but cannot Mega Evolve without being
     * purified first — which rerolls its IVs, so the spread being ranked is gone.
     */
    public function testAShadowEvolvesIntoShadowsAndNeverIntoAMega(): void
    {
        self::assertSame(
            ['ralts_shadow', 'ralts_shadow > kirlia_shadow', 'ralts_shadow > kirlia_shadow > gardevoir_shadow'],
            $this->walk('ralts_shadow'),
        );
    }

    /**
     * The source lists no evolution on some shadows (Eevee): the regular form's links
     * hold for its shadow too.
     */
    public function testAShadowFollowsTheLinksOfItsRegularForm(): void
    {
        self::assertSame(['eevee_shadow', 'eevee_shadow > umbreon_shadow'], $this->walk('eevee_shadow'));
    }

    /**
     * Galarian Slowbro shares its Pokédex number with Mega Slowbro, but only the plain
     * form Mega Evolves.
     */
    public function testARegionalFormDoesNotBorrowTheMegaOfItsNumber(): void
    {
        self::assertSame(['slowbro_galarian'], $this->walk('slowbro_galarian'));
    }

    public function testALinkToAFormTheCatalogueLacksIsSkipped(): void
    {
        self::assertSame(['wyrdeer_parent'], $this->walk('wyrdeer_parent'));
    }

    public function testOnlyAFormOneStepFromItsMegaMeasuresTheMegaGain(): void
    {
        $fromKirlia = $this->tree()->from($this->catalog()->find(new SpeciesId('kirlia')) ?? self::fail());
        $fromGardevoir = $this->tree()->from($this->catalog()->find(new SpeciesId('gardevoir')) ?? self::fail());

        self::assertSame(
            [false, false, false, false, false],
            array_map(static fn(EvolutionPath $path): bool => $path->isMegaEvolution(), $fromKirlia),
            'from Kirlia the gain would mostly measure the ordinary evolution',
        );
        self::assertSame(
            [false, true],
            array_map(static fn(EvolutionPath $path): bool => $path->isMegaEvolution(), $fromGardevoir),
        );
    }

    public function testAPathLeadsToTheOnesItIsTheStartOf(): void
    {
        [$ralts, $kirlia, $gardevoir] = array_map(
            fn(string $id): Species => $this->catalog()->find(new SpeciesId($id)) ?? self::fail(),
            ['ralts', 'kirlia', 'gardevoir'],
        );
        $toKirlia = new EvolutionPath([$ralts, $kirlia]);

        self::assertTrue($toKirlia->leadsTo($toKirlia->then($gardevoir)));
        self::assertFalse($toKirlia->leadsTo($toKirlia), 'a path does not lead to itself');
        self::assertFalse($toKirlia->leadsTo(new EvolutionPath([$kirlia, $gardevoir])), 'another start');
    }

    /**
     * @return list<string>
     */
    private function walk(string $id): array
    {
        $asked = $this->catalog()->find(new SpeciesId($id)) ?? self::fail(\sprintf('"%s" is not in the fixture.', $id));

        return array_map(static fn(EvolutionPath $path): string => implode(' > ', array_map(
            static fn(Species $stage): string => $stage->id->value,
            $path->stages,
        )), $this->tree()->from($asked));
    }

    private function tree(): EvolutionTree
    {
        return new EvolutionTree($this->catalog());
    }

    private function catalog(): InMemorySpeciesCatalog
    {
        return new InMemorySpeciesCatalog([
            $this->species('ralts', 280, evolutions: ['kirlia']),
            $this->species('kirlia', 281, evolutions: ['gardevoir', 'gallade']),
            $this->species('gardevoir', 282),
            $this->species('gardevoir_mega', 282, ['mega']),
            $this->species('gallade', 475),
            $this->species('gallade_mega', 475, ['mega']),
            $this->species('ralts_shadow', 280, ['shadow'], ['kirlia_shadow']),
            $this->species('kirlia_shadow', 281, ['shadow'], ['gardevoir_shadow']),
            $this->species('gardevoir_shadow', 282, ['shadow']),
            $this->species('charizard', 6),
            $this->species('charizard_mega_x', 6, ['mega_x']),
            $this->species('charizard_mega_y', 6, ['mega_y']),
            $this->species('eevee', 133, evolutions: ['umbreon']),
            $this->species('eevee_shadow', 133, ['shadow']),
            $this->species('umbreon', 197),
            $this->species('umbreon_shadow', 197, ['shadow']),
            $this->species('slowbro', 80),
            $this->species('slowbro_galarian', 80, ['galarian']),
            $this->species('slowbro_mega', 80, ['mega']),
            $this->species('wyrdeer_parent', 234, evolutions: ['wyrdeer_missing']),
        ]);
    }

    /**
     * @param list<string> $forms
     * @param list<string> $evolutions
     */
    private function species(string $id, int $dex, array $forms = [], array $evolutions = []): Species
    {
        return new Species(
            new SpeciesId($id),
            new DexNumber($dex),
            new BaseStats(100, 100, 100),
            shadowEligible: true,
            forms: $forms,
            evolutions: array_map(static fn(string $to): SpeciesId => new SpeciesId($to), $evolutions),
        );
    }
}
