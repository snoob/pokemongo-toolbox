<?php

declare(strict_types=1);

namespace App\UI\Cli\Settings;

use App\Domain\Pvp\Model\League;
use App\Infrastructure\Json\JsonValue;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

use function Laravel\Prompts\multiselect;

/**
 * The wizard's league question. In a terminal, a list to tick with the arrows and the
 * space bar (`laravel/prompts`); elsewhere — input piped or given by a test, Windows,
 * where that needs `stty` — the same list typed as names separated by commas.
 *
 * Picking nothing (or "-" when typed) unsets the setting: each species is then ranked in
 * its own leagues, the Mega Editions included.
 */
final readonly class LeaguePicker
{
    private const string NONE = 'none: each species in its own leagues, the Mega Editions included';

    /**
     * @param null|string|list<string> $current
     * @param list<string>             $proposed ticked when nothing is set
     *
     * @return ?list<string> null to unset
     */
    public static function pick(
        InputInterface $input,
        SymfonyStyle $io,
        string $question,
        string|array|null $current,
        array $proposed,
    ): ?array {
        $ticked = (array) ($current ?? $proposed);

        return ArrowPrompts::available($input)
            ? self::checkboxes($question, $ticked)
            : self::typed($io, $question, $ticked);
    }

    /**
     * @param list<string> $ticked
     *
     * @return ?list<string>
     */
    private static function checkboxes(string $question, array $ticked): ?array
    {
        $picked = JsonValue::asStringList(multiselect(
            label: $question,
            options: self::labels(),
            default: $ticked,
            scroll: \count(League::cases()),
            hint: 'Space ticks, Enter confirms. Mega Editions unticked: no megas.',
        ));

        return [] === $picked ? null : $picked;
    }

    /**
     * @param list<string> $ticked
     *
     * @return ?list<string>
     */
    private static function typed(SymfonyStyle $io, string $question, array $ticked): ?array
    {
        $choice = new ChoiceQuestion(
            $question . ' Several separated by commas',
            [...self::labels(), SettingQuestion::UNSET => self::NONE],
            implode(',', $ticked),
        );
        $choice->setMultiselect(true);
        $picked = JsonValue::asStringList($io->askQuestion($choice));

        return \in_array(SettingQuestion::UNSET, $picked, true) ? null : $picked;
    }

    /**
     * @return array<string, string> league => label
     */
    private static function labels(): array
    {
        $labels = [];

        foreach (League::cases() as $league) {
            $labels[$league->value] = $league->label();
        }

        return $labels;
    }
}
