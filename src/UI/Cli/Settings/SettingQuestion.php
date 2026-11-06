<?php

declare(strict_types=1);

namespace App\UI\Cli\Settings;

use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * One question of the wizard about one setting. The answer is read by `$read`, which
 * throws to ask again. Enter keeps the current setting without reading it again — no
 * second lookup of a place already found — or, with none, takes the proposed answer as
 * if typed. "-" unsets it.
 */
final readonly class SettingQuestion
{
    public const string UNSET = '-';

    /**
     * @param null|string|list<string>                 $current  the setting as it stands
     * @param ?string                                  $proposed shown when nothing is set
     * @param \Closure(string): (string|list<string>) $read
     *
     * @return null|string|list<string> null to unset
     */
    public static function ask(
        SymfonyStyle $io,
        string $question,
        string|array|null $current,
        ?string $proposed,
        \Closure $read,
    ): string|array|null {
        $shown = null === $current ? $proposed : implode(' ', (array) $current);
        $result = $current;

        $io->ask($question, $shown, static function (mixed $answer) use ($shown, $current, $read, &$result): string {
            $answer = trim(\is_string($answer) ? $answer : '');

            $result = match ($answer) {
                self::UNSET => null,
                '', $shown => null === $current && null !== $shown ? $read($shown) : $current,
                default => $read($answer),
            };

            return $answer;
        });

        return $result;
    }
}
