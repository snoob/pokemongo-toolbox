<?php

declare(strict_types=1);

namespace App\Infrastructure\Inventory;

use App\Domain\Inventory\Model\Inventory;
use App\Domain\Inventory\Model\InventoryEntry;
use App\Domain\Pokemon\Model\IvSpread;
use App\Domain\Pokemon\Model\SpeciesId;
use App\Domain\Pvp\Model\League;
use App\Infrastructure\Json\JsonPath;
use App\Infrastructure\Json\JsonValue;

/**
 * The inventory file's format, apart from reading and writing it. Versioned, so that a
 * later format can tell an older file from a broken one.
 */
final readonly class InventoryDocument
{
    private const int VERSION = 1;

    public static function encode(Inventory $inventory): string
    {
        return json_encode(
            [
                'version' => self::VERSION,
                'nextId' => $inventory->nextId(),
                'entries' => array_map(
                    /** @return array<string, int|string> */
                    static fn(InventoryEntry $entry): array => [
                        'id' => $entry->id,
                        'species' => $entry->species->value,
                        'iv' => (string) $entry->iv,
                        'league' => $entry->league->value,
                        'addedAt' => $entry->addedAt->format(\DATE_ATOM),
                    ],
                    $inventory->entries(),
                ),
            ],
            \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR,
        ) . "\n";
    }

    /**
     * @throws \UnexpectedValueException naming what is wrong
     */
    public static function decode(string $json): Inventory
    {
        try {
            $document = JsonValue::asArray(json_decode($json, true, flags: \JSON_THROW_ON_ERROR));
        } catch (\JsonException $e) {
            throw new \UnexpectedValueException($e->getMessage(), previous: $e);
        }

        if (null === $document || self::VERSION !== JsonPath::intAt($document, 'version')) {
            throw new \UnexpectedValueException(\sprintf('expected version %d', self::VERSION));
        }

        $entries = [];

        foreach (JsonPath::arrayListAt($document, 'entries') as $index => $entry) {
            $entries[] = self::entry($entry) ?? throw new \UnexpectedValueException(\sprintf(
                'entry %d is malformed',
                $index,
            ));
        }

        return new Inventory($entries, max(1, JsonPath::intAt($document, 'nextId') ?? 1));
    }

    /**
     * @param array<array-key, mixed> $entry
     */
    private static function entry(array $entry): ?InventoryEntry
    {
        $id = JsonPath::intAt($entry, 'id');
        $species = JsonPath::stringAt($entry, 'species');
        $iv = JsonPath::stringAt($entry, 'iv');
        $league = League::tryFrom(JsonPath::stringAt($entry, 'league') ?? '');
        $addedAt = JsonPath::stringAt($entry, 'addedAt');

        if (null === $id || null === $species || null === $iv || null === $league || null === $addedAt) {
            return null;
        }

        try {
            return new InventoryEntry(
                $id,
                new SpeciesId($species),
                IvSpread::fromString($iv),
                $league,
                new \DateTimeImmutable($addedAt),
            );
        } catch (\Exception) {
            return null;
        }
    }
}
