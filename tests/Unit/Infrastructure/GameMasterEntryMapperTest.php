<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure;

use App\Infrastructure\Build\BaseStatsReader;
use App\Infrastructure\Build\FormLabels;
use App\Infrastructure\Build\GameMasterEntryMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GameMasterEntryMapperTest extends TestCase
{
    private const array GENGAR = [
        'speciesId' => 'gengar',
        'speciesName' => 'Gengar',
        'dex' => 94,
        'baseStats' => ['atk' => 261, 'def' => 149, 'hp' => 155],
        'tags' => ['shadoweligible'],
        'eliteMoves' => ['SHADOW_PUNCH'],
    ];

    public function testItFlattensAUsableEntry(): void
    {
        $mapped = new GameMasterEntryMapper(new BaseStatsReader(), new FormLabels())->map(self::GENGAR);

        self::assertSame(
            [
                'id' => 'gengar',
                'dex' => 94,
                'forms' => [],
                'atk' => 261,
                'def' => 149,
                'sta' => 155,
                'shadow' => true,
                'super' => false,
                'elite' => ['SHADOW_PUNCH'],
                'base' => true,
            ],
            $mapped,
        );
    }

    public function testAFormIsStoredAsSlugsRatherThanAName(): void
    {
        $mapped = new GameMasterEntryMapper(new BaseStatsReader(), new FormLabels())->map([
            ...self::GENGAR,
            'speciesId' => 'raichu_alolan_shadow',
            'speciesName' => 'Raichu (Alolan) (Shadow)',
        ]);

        self::assertNotNull($mapped);
        self::assertSame(['alolan', 'shadow'], $mapped['forms'], 'nested segments are kept in order');
        self::assertFalse($mapped['base']);
    }

    /**
     * An incomplete upstream entry must be dropped at the boundary: writing a zero
     * would produce a species file that throws on every later read.
     *
     * @param array<string, mixed> $entry
     */
    #[DataProvider('unusableEntries')]
    public function testAnUnusableEntryIsDropped(array $entry): void
    {
        self::assertNull(new GameMasterEntryMapper(new BaseStatsReader(), new FormLabels())->map($entry));
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function unusableEntries(): iterable
    {
        yield 'missing id' => [[...self::GENGAR, 'speciesId' => null]];
        yield 'missing dex' => [[...self::GENGAR, 'dex' => null]];
        yield 'dex below one' => [[...self::GENGAR, 'dex' => 0]];
        yield 'no base stats' => [[...self::GENGAR, 'baseStats' => null]];
        yield 'zero attack' => [[...self::GENGAR, 'baseStats' => ['atk' => 0, 'def' => 149, 'hp' => 155]]];
        yield 'missing stamina' => [[...self::GENGAR, 'baseStats' => ['atk' => 261, 'def' => 149]]];
    }
}
