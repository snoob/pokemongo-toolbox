<?php

declare(strict_types=1);

namespace App\UI\Cli\Settings;

use App\Domain\Inventory\Model\Account;
use App\Infrastructure\Json\JsonValue;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

use function Laravel\Prompts\select;

/**
 * `switch` without a name: the accounts that have an inventory, to pick with the arrows
 * in a terminal, typed elsewhere as `LeaguePicker` does. A new one is typed after
 * picking "Another account".
 */
final readonly class AccountPicker
{
    private const string ANOTHER = '+';

    /**
     * @param list<Account> $known
     *
     * @throws \InvalidArgumentException when the name typed cannot be an account
     */
    public static function pick(InputInterface $input, SymfonyStyle $io, Account $current, array $known): Account
    {
        $options = [];

        foreach ([$current, ...$known] as $account) {
            $options[$account->name] = $account->equals($current) ? \sprintf('%s (current)', $account) : $account->name;
        }

        $options[self::ANOTHER] = 'Another account…';
        $question = 'Which game account should the inventory commands use?';

        $picked = JsonValue::asString(
            ArrowPrompts::available($input)
                ? select(label: $question, options: $options, default: $current->name, scroll: \count($options))
                : $io->askQuestion(new ChoiceQuestion($question, $options, $current->name)),
        );

        if (self::ANOTHER === $picked) {
            $picked = JsonValue::asString($io->ask('Its trainer name?'));
        }

        return new Account($picked ?? $current->name);
    }
}
