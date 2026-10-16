<?php

declare(strict_types=1);

namespace App\Domain\Pokemon\Service;

use App\Domain\Pokemon\Model\EvolutionPath;
use App\Domain\Pokemon\Model\Species;
use App\Domain\Pokemon\Port\SpeciesCatalog;

/**
 * Everything a form can become: its evolutions, theirs, and the mega of each.
 */
final readonly class EvolutionTree
{
    public function __construct(
        private SpeciesCatalog $catalog,
    ) {}

    /**
     * The asked form first, then each form it can become, depth first, every one followed
     * by its mega: Ralts, Kirlia, Gardevoir, Mega Gardevoir, Gallade, Mega Gallade.
     *
     * @return non-empty-list<EvolutionPath>
     */
    public function from(Species $asked): array
    {
        return $this->walk(new EvolutionPath([$asked]));
    }

    /**
     * @return non-empty-list<EvolutionPath>
     */
    private function walk(EvolutionPath $path): array
    {
        $current = $path->last();
        $paths = [$path];

        foreach ($this->megasOf($current) as $mega) {
            $paths[] = $path->then($mega);
        }

        foreach ($this->evolutionsOf($current) as $next) {
            if ($this->alreadyOn($path, $next)) {
                continue;
            }

            $paths = [...$paths, ...$this->walk($path->then($next))];
        }

        return $paths;
    }

    /**
     * Guards against a loop in the source data rather than recursing forever.
     */
    private function alreadyOn(EvolutionPath $path, Species $next): bool
    {
        foreach ($path->stages as $stage) {
            if ($stage->id->equals($next->id)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Only the plain form Mega Evolves: a regional form shares the Pokédex number of a
     * mega without reaching it, and a shadow must be purified first — which rerolls the
     * IVs being ranked.
     *
     * @return list<Species>
     */
    private function megasOf(Species $species): array
    {
        return $species->isBaseForm() ? $this->catalog->megaFormsOf($species->dex) : [];
    }

    /**
     * A shadow stays a shadow when it evolves. The source leaves some shadows without
     * links (Eevee), so they follow their regular form's. An evolution with no shadow
     * entry is left out: ranking the regular form instead would answer for a Pokémon the
     * shadow cannot become.
     *
     * @return list<Species>
     */
    private function evolutionsOf(Species $species): array
    {
        $regular = $species->isShadow() ? $this->catalog->find($species->id->regular()) : $species;
        $evolutions = [];

        foreach ($regular->evolutions ?? [] as $id) {
            $next = $this->catalog->find($species->isShadow() ? $id->shadow() : $id);

            if (null !== $next) {
                $evolutions[] = $next;
            }
        }

        return $evolutions;
    }
}
