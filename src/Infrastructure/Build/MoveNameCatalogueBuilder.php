<?php

declare(strict_types=1);

namespace App\Infrastructure\Build;

/**
 * Build-time only: the move names per locale.
 *
 * English comes from the game itself, so it is always complete. French comes from the
 * Pokédex move table, which does not know the game's own variants — `_PLUS` mega moves,
 * `HIDDEN_POWER_FIRE`, `AURA_WHEEL_DARK`. Those resolve through the longest translatable
 * prefix with the remainder appended, and fall back to English when even that fails.
 */
final readonly class MoveNameCatalogueBuilder
{
    public function __construct(
        private PokeApiMoveNames $frenchNames,
    ) {}

    /**
     * @param list<array{id: string, name: string, ...}> $gameMoves
     *
     * @return array<string, array<string, string>> locale => (move id => name)
     */
    public function build(array $gameMoves): array
    {
        $french = $this->frenchNames->bySlug();
        $catalogues = ['en' => [], 'fr' => []];

        foreach ($gameMoves as $move) {
            $catalogues['en'][$move['id']] = $move['name'];
            $translated = $this->translate($move['id'], $french);

            if (null !== $translated) {
                $catalogues['fr'][$move['id']] = $translated;
            }
        }

        return $catalogues;
    }

    /**
     * @param array<string, string> $french
     */
    private function translate(string $moveId, array $french): ?string
    {
        $segments = explode('_', strtolower($moveId));

        // Longest prefix wins: FUTURE_SIGHT_PLUS resolves through FUTURE_SIGHT, and
        // HIDDEN_POWER_FIRE through HIDDEN_POWER.
        for ($length = \count($segments); $length > 0; --$length) {
            $name = $french[implode('-', \array_slice($segments, 0, $length))] ?? null;

            if (null === $name) {
                continue;
            }

            return $this->withSuffix($name, \array_slice($segments, $length));
        }

        return null;
    }

    /**
     * @param list<string> $remainder
     */
    private function withSuffix(string $name, array $remainder): string
    {
        if ([] === $remainder) {
            return $name;
        }

        // "+" is how the game itself writes a mega move; anything else stays explicit.
        if (['plus'] === $remainder) {
            return $name . '+';
        }

        return \sprintf('%s (%s)', $name, ucfirst(implode(' ', $remainder)));
    }
}
