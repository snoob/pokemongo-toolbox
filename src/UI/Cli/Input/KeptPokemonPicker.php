<?php

declare(strict_types=1);

namespace App\UI\Cli\Input;

use App\Application\Inventory\ListInventory\InventoryLine;
use App\Infrastructure\Json\JsonValue;
use App\UI\Cli\Settings\ArrowPrompts;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

use function Laravel\Prompts\select;

/**
 * Several kept Pokémon match what was typed: one is picked with the arrows in a
 * terminal, typed elsewhere, as `AccountPicker` does.
 */
final readonly class KeptPokemonPicker
{
    /**
     * @param non-empty-list<InventoryLine> $lines in the order shown
     * @param \Closure(InventoryLine): string $label
     */
    public static function pick(
        InputInterface $input,
        SymfonyStyle $io,
        string $question,
        array $lines,
        \Closure $label,
    ): InventoryLine {
        $byKey = [];
        $options = [];

        foreach ($lines as $line) {
            // Not the bare number: PHP would turn it into an integer key, and the arrows
            // answer with the key.
            $key = 'entry-' . $line->entry->id;
            $byKey[$key] = $line;
            $options[$key] = $label($line);
        }

        $picked = JsonValue::asString(
            ArrowPrompts::available($input)
                ? select(label: $question, options: $options, scroll: min(15, \count($options)))
                : $io->askQuestion(new ChoiceQuestion($question, array_values($options))),
        );

        // The arrows answer with the key, the typed fallback with the label.
        $key = $picked ?? '';
        $key = \array_key_exists($key, $byKey) ? $key : array_search($key, $options, true);

        return $byKey[(string) $key] ?? throw new \LogicException(\sprintf('"%s" is not among the choices.', $picked));
    }
}
