<?php

declare(strict_types=1);

namespace App\Domain\Pokemon\Port;

use App\Domain\Pokemon\Model\DexNumber;
use App\Domain\Pokemon\Model\Species;
use App\Domain\Pokemon\Model\SpeciesId;

interface SpeciesCatalog
{
    /**
     * Every species matching an English name, a French name or a Pokédex number.
     * Matching is case- and accent-insensitive. An empty list means "not found";
     * several entries mean the identifier is ambiguous (alternate forms).
     *
     * @return list<Species>
     */
    public function search(string $identifier): array;

    public function find(SpeciesId $id): ?Species;

    /**
     * The default form sharing this Pokédex number — the one you actually power up
     * before Mega Evolving. Null when the species has no plain form (Giratina).
     */
    public function baseFormOf(DexNumber $dex): ?Species;

    /**
     * The mega forms sharing this Pokédex number. Usually none or one; Charizard,
     * Raichu and Mewtwo have two (X and Y), which makes "--mega" ambiguous for them.
     *
     * @return list<Species>
     */
    public function megaFormsOf(DexNumber $dex): array;
}
