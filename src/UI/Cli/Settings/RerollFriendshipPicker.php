<?php

declare(strict_types=1);

namespace App\UI\Cli\Settings;

use App\Infrastructure\Json\JsonValue;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

use function Laravel\Prompts\select;

/**
 * The wizard's question on rerolling IVs through a second account: one friendship level
 * to pick, with the arrows in a terminal (`laravel/prompts`), typed elsewhere as
 * `LeaguePicker` does. The level is saved under the name `--floor` gives it; "no" unsets
 * the floor.
 *
 * A floor written by hand that no friendship gives ("raid", "4") is offered as it is, so
 * that Enter keeps it.
 */
final readonly class RerollFriendshipPicker
{
    private const string NONE = 'no';

    /**
     * Trade friendships only: a lucky trade is not planned, and stays an option.
     *
     * @var array<string, string>
     */
    private const array LEVELS = [
        self::NONE => 'No reroll',
        'good' => 'Good Friend — floor 1',
        'great' => 'Great Friend — floor 2',
        'ultra' => 'Ultra Friend — floor 3',
        'best' => 'Best Friend — floor 5',
    ];

    /**
     * @param null|string|list<string> $current
     *
     * @return ?string null to unset
     */
    public static function pick(
        InputInterface $input,
        SymfonyStyle $io,
        string $question,
        string|array|null $current,
    ): ?string {
        $current = null === $current ? self::NONE : implode(' ', (array) $current);
        $options = \array_key_exists($current, self::LEVELS)
            ? self::LEVELS
            : [...self::LEVELS, $current => \sprintf('Keep %s', $current)];

        $picked = JsonValue::asString(
            ArrowPrompts::available($input)
                ? select(label: $question, options: $options, default: $current, scroll: \count($options))
                : $io->askQuestion(self::typed($question, $options, $current)),
        );

        return null === $picked || self::NONE === $picked ? null : $picked;
    }

    /**
     * Typed, "Best" is "best".
     *
     * @param array<string, string> $options
     */
    private static function typed(string $question, array $options, string $current): ChoiceQuestion
    {
        $choice = new ChoiceQuestion($question, $options, $current);
        $choice->setNormalizer(static fn(mixed $answer): string => strtolower(trim(
            \is_string($answer) ? $answer : '',
        )));

        return $choice;
    }
}
