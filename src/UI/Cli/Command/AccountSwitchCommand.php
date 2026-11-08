<?php

declare(strict_types=1);

namespace App\UI\Cli\Command;

use App\Domain\Inventory\Model\Account;
use App\Domain\Inventory\Port\InventoryRepository;
use App\Infrastructure\Json\JsonValue;
use App\Infrastructure\Settings\SettingsFile;
use App\UI\Cli\Settings\AccountPicker;
use App\UI\Cli\Settings\ConfigurableOptions;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Changes the game account every command uses by default — the `global.account`
 * setting — without remembering its key. It writes the settings rather than reads them,
 * so it runs whatever the file holds (`ConfigurableOptions::OWN_SECTIONS`).
 */
#[AsCommand(
    name: 'app:account:switch',
    aliases: ['switch'],
    description: 'Change the game account the inventory commands use',
)]
final class AccountSwitchCommand extends Command
{
    private const string SETTING = 'account';

    public function __construct(
        private readonly SettingsFile $settings,
        private readonly InventoryRepository $inventories,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this->addArgument(
            'account',
            InputArgument::OPTIONAL,
            'Trainer name of the account; without it, pick among those with an inventory',
        )->setHelp(<<<'HELP'
            Sets the account the inventory commands use when --account is left out
            (the global.account setting). Each account keeps its own inventory.

              <info>%command.full_name% snoob2555</info>   from now on
              <info>%command.full_name%</info>             pick among the accounts you keep Pokémon for

            For a single command, --account still wins: <info>pogo inventory:list -a snoob2</info>
            HELP);
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $sections = $this->settings->load();
            $current = new Account(
                JsonValue::asString($sections[ConfigurableOptions::GLOBAL][self::SETTING] ?? null) ?? Account::DEFAULT,
            );
            $account = $this->chosen($input, $io, $current);
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::INVALID;
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        if (null === $account) {
            return Command::SUCCESS;
        }

        $sections[ConfigurableOptions::GLOBAL][self::SETTING] = $account->name;

        try {
            $this->settings->save($sections);
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $this->confirm($io, $account);

        return Command::SUCCESS;
    }

    /**
     * Null when there is nothing to change: no name given and no terminal to ask in,
     * where the accounts are listed instead.
     *
     * @throws \InvalidArgumentException when the name cannot be an account
     */
    private function chosen(InputInterface $input, SymfonyStyle $io, Account $current): ?Account
    {
        $typed = JsonValue::asString($input->getArgument('account'));

        if (null !== $typed) {
            return new Account($typed);
        }

        $known = $this->inventories->accounts();

        if ($input->isInteractive()) {
            return AccountPicker::pick($input, $io, $current, $known);
        }

        $io->text(\sprintf('Current account: <info>%s</info>', $current));

        [] === $known
            ? $io->text('No account keeps Pokémon yet.')
            : $io->listing(array_map(static fn(Account $account): string => $account->name, $known));

        return null;
    }

    /**
     * A name never used before may be a typo: an account with no inventory says so.
     */
    private function confirm(SymfonyStyle $io, Account $account): void
    {
        $kept = \count($this->inventories->load($account)->entries());

        $io->success(
            0 === $kept
                ? \sprintf('Now using "%s", which has no inventory yet; "inventory:add" starts one.', $account)
                : \sprintf('Now using "%s": %d Pokémon kept.', $account, $kept),
        );
    }
}
