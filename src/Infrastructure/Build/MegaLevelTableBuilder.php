<?php

declare(strict_types=1);

namespace App\Infrastructure\Build;

use App\Infrastructure\Json\JsonPath;
use App\Infrastructure\Json\JsonValue;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Build-time only: what each Mega Level changes, and how far each species can climb.
 *
 * Only the last level carries `selfCpBoostAdditionalLevel`: the mega then evolves as if
 * it were that many Pokémon levels higher, which lowers the CP to stop at before
 * evolving. The other levels only shorten the cooldown and hand out extra candy.
 */
final readonly class MegaLevelTableBuilder
{
    private const string GAME_MASTER_URL = 'https://raw.githubusercontent.com/PokeMiners/game_masters/master/latest/latest.json';

    /**
     * Multiplier applied to the base power of the mega-exclusive Charged Attack, by
     * Mega Level (Base / High / Max / Super Max).
     *
     * This one is NOT in the game master — the exhaustive key list of
     * MEGA_EVOLUTION_LEVEL_* holds no power multiplier. It comes from the community
     * (pokemongo.fandom.com/wiki/Mega_Evolution, citing a Silph Road analysis), so it is
     * the one figure here that no upstream file can confirm. Kept beside the generated
     * values so the runtime reads a single file, with its provenance stated.
     */
    private const array MOVE_POWER_MULTIPLIERS = ['1' => 1.0, '2' => 1.1, '3' => 1.2, '4' => 1.3];

    public function __construct(
        private HttpClientInterface $http,
        private MegaLevelTemplateId $templates,
    ) {}

    /**
     * @return array{generatedAt: string, additionalLevels: array<array-key, int>, cooldownDays: array<array-key, int>, extraCandy: array<array-key, int>, movePowerMultipliers: array<array-key, float>, maxLevelByDex: array<array-key, int>}
     */
    public function build(): array
    {
        $additional = [];
        $cooldown = [];
        $candy = [];
        $maxByDex = [];

        foreach ($this->levelTemplates() as $id => $template) {
            $level = $this->templates->levelOf($id);
            $settings = JsonPath::arrayAt($template['data'] ?? $template, 'megaEvoLevelSettings');
            $additional[(string) $level] = $this->additionalLevelsIn($template);
            $cooldown[(string) $level] = $this->cooldownDaysIn($settings);
            $candy[(string) $level] =
                JsonValue::asInt(JsonPath::arrayAt($settings, 'effects')['sameTypeExtraCatchCandy'] ?? null) ?? 0;
            $dex = $this->templates->dexOf($id);

            if (null !== $dex) {
                $maxByDex[(string) $dex] = max($maxByDex[(string) $dex] ?? 0, $level);
            }
        }

        ksort($additional);
        ksort($cooldown);
        ksort($candy);
        ksort($maxByDex);

        return [
            'generatedAt' => date(\DATE_ATOM),
            'additionalLevels' => $additional,
            'cooldownDays' => $cooldown,
            'extraCandy' => $candy,
            'movePowerMultipliers' => self::MOVE_POWER_MULTIPLIERS,
            'maxLevelByDex' => $maxByDex,
        ];
    }

    /**
     * @param array<array-key, mixed>|null $settings
     */
    private function cooldownDaysIn(?array $settings): int
    {
        $ms = JsonValue::asFloat(JsonPath::arrayAt($settings, 'cooldown')['durationMs'] ?? null);

        return (int) ($ms / 86_400_000);
    }

    /**
     * @return iterable<string, array<array-key, mixed>>
     */
    private function levelTemplates(): iterable
    {
        foreach (JsonValue::asArrayList($this->http->request('GET', self::GAME_MASTER_URL)->toArray()) as $template) {
            $id = JsonValue::asString($template['templateId'] ?? null);

            if (null !== $id && str_starts_with($id, MegaLevelTemplateId::PREFIX)) {
                yield $id => $template;
            }
        }
    }

    /**
     * @param array<array-key, mixed> $template
     */
    private function additionalLevelsIn(array $template): int
    {
        $settings = JsonPath::arrayAt($template['data'] ?? $template, 'megaEvoLevelSettings');

        return JsonValue::asInt(JsonPath::arrayAt($settings, 'effects')['selfCpBoostAdditionalLevel'] ?? null) ?? 0;
    }
}
