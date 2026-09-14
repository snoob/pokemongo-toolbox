<?php

declare(strict_types=1);

namespace App\Domain\Pokemon\Port;

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
}
