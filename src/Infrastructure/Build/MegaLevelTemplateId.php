<?php

declare(strict_types=1);

namespace App\Infrastructure\Build;

/**
 * Build-time only: reads what a MEGA_EVOLUTION_LEVEL template id encodes.
 *
 * "MEGA_EVOLUTION_LEVEL_4_V0026_POKEMON_RAICHU" carries both the level and the species;
 * the global "MEGA_EVOLUTION_LEVEL_4" carries only the level.
 */
final readonly class MegaLevelTemplateId
{
    public const string PREFIX = 'MEGA_EVOLUTION_LEVEL_';

    public function levelOf(string $templateId): int
    {
        $matches = [];
        preg_match('#^(\d+)#', substr($templateId, \strlen(self::PREFIX)), $matches);

        return (int) ($matches[1] ?? 0);
    }

    public function dexOf(string $templateId): ?int
    {
        $matches = [];

        return 1 === preg_match('#_V(\d{4})_POKEMON_#', $templateId, $matches) ? (int) ($matches[1] ?? 0) : null;
    }
}
