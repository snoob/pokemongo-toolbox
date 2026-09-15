<?php

declare(strict_types=1);

namespace App\Infrastructure\Catalog;

use App\Domain\Pokemon\Model\BaseStats;
use App\Domain\Pokemon\Model\DexNumber;
use App\Domain\Pokemon\Model\MoveId;
use App\Domain\Pokemon\Model\Species;
use App\Domain\Pokemon\Model\SpeciesId;
use App\Infrastructure\Json\JsonPath;

/**
 * Turns the generated species file into domain objects. Nothing beyond this class
 * ever sees the file's shape.
 */
final readonly class SpeciesFileReader
{
    public function __construct(
        private string $file,
    ) {}

    /**
     * @return list<Species>
     */
    public function read(): array
    {
        if (!is_file($this->file)) {
            throw new \RuntimeException(\sprintf(
                'Species file "%s" is missing; run "bin/console pogo:data:build".',
                $this->file,
            ));
        }

        $entries = JsonPath::arrayListAt(
            json_decode((string) file_get_contents($this->file), true, flags: \JSON_THROW_ON_ERROR),
            'species',
        );

        if ([] === $entries) {
            throw new \RuntimeException(\sprintf(
                'Species file "%s" is malformed or empty; rebuild it with "bin/console pogo:data:build".',
                $this->file,
            ));
        }

        /** @var list<array{id: string, dex: int, atk: int, def: int, sta: int, shadow: bool, super: bool, base: bool, forms: list<string>, elite: list<string>}> $entries */
        return array_map($this->toSpecies(...), $entries);
    }

    /**
     * @param array{id: string, dex: int, atk: int, def: int, sta: int, shadow: bool, super: bool, base: bool, forms: list<string>, elite: list<string>} $entry
     */
    private function toSpecies(array $entry): Species
    {
        return new Species(
            id: new SpeciesId($entry['id']),
            dex: new DexNumber($entry['dex']),
            baseStats: new BaseStats($entry['atk'], $entry['def'], $entry['sta']),
            shadowEligible: $entry['shadow'],
            superMega: $entry['super'],
            forms: $entry['forms'],
            eliteMoves: array_map(static fn(string $move): MoveId => new MoveId($move), $entry['elite']),
        );
    }
}
