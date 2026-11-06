<?php

declare(strict_types=1);

namespace App\UI\Cli\Input;

use App\Domain\Inventory\Model\Account;
use App\Infrastructure\Json\JsonValue;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * The `--account` option of the inventory commands. Set `inventory.account` to change
 * the default.
 */
final readonly class AccountOption
{
    private const string NAME = 'account';

    public static function addTo(Command $command): void
    {
        $command->addOption(
            self::NAME,
            'a',
            InputOption::VALUE_REQUIRED,
            'Game account whose inventory to use',
            Account::DEFAULT,
        );
    }

    /**
     * @throws \InvalidArgumentException when the name cannot be an account
     */
    public static function parse(InputInterface $input): Account
    {
        return new Account(JsonValue::asString($input->getOption(self::NAME)) ?? Account::DEFAULT);
    }
}
