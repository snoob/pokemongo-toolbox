<?php

declare(strict_types=1);

namespace App\Infrastructure\Build;

use App\Domain\Pvp\Model\PokemonLevel;
use App\Infrastructure\Json\JsonPath;
use App\Infrastructure\Json\JsonValue;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Build-time only: extracts the CP multipliers from the game files and fills in the
 * half levels the game does not publish.
 */
final readonly class CpMultiplierTableBuilder
{
    private const string GAME_MASTER_URL = 'https://raw.githubusercontent.com/PokeMiners/game_masters/master/latest/latest.json';
    private const string LEVEL_SETTINGS = 'PLAYER_LEVEL_SETTINGS';

    public function __construct(
        private HttpClientInterface $http,
    ) {}

    /**
     * @return array{generatedAt: string, multipliers: array<string, float>}
     */
    public function build(): array
    {
        $whole = $this->wholeLevelMultipliers();
        $multipliers = [];

        for ($value = PokemonLevel::MIN; $value <= PokemonLevel::BEST_BUDDY_CAP; $value += 0.5) {
            $level = new PokemonLevel($value);
            $index = (int) floor($value) - 1;
            $current = $whole[$index] ?? null;

            if (null === $current) {
                throw new \RuntimeException(\sprintf('The CP multiplier table stops before level %s.', $level));
            }

            // Whole levels come straight from the game; half levels are the geometric
            // mean the game uses between two consecutive multipliers.
            $multipliers[(string) $level] = 0.0 === fmod($value, 1.0)
                ? $current
                : sqrt(($current ** 2 + ($whole[$index + 1] ?? $current) ** 2) / 2);
        }

        return ['generatedAt' => date(\DATE_ATOM), 'multipliers' => $multipliers];
    }

    /**
     * @return list<float>
     */
    private function wholeLevelMultipliers(): array
    {
        $templates = JsonValue::asArrayList($this->http->request('GET', self::GAME_MASTER_URL)->toArray());

        foreach ($templates as $template) {
            if (self::LEVEL_SETTINGS !== JsonValue::asString($template['templateId'] ?? null)) {
                continue;
            }

            $multipliers = JsonPath::floatListAt(
                JsonPath::arrayAt($template['data'] ?? null, 'playerLevel'),
                'cpMultiplier',
            );

            if ([] !== $multipliers) {
                return $multipliers;
            }

            break;
        }

        throw new \RuntimeException(\sprintf('%s carries no CP multiplier table.', self::LEVEL_SETTINGS));
    }
}
