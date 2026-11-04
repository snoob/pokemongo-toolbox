<?php

declare(strict_types=1);

namespace App\UI\Cli\Command;

use App\Application\Inventory\RemoveFromInventory\RemoveFromInventoryCommand;
use App\Application\Inventory\RemoveFromInventory\RemoveFromInventoryHandler;
use App\Domain\Inventory\Exception\EntryNotFound;
use App\Infrastructure\Json\JsonValue;
use App\Infrastructure\Naming\SpeciesNameResolver;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:inventory:remove',
    aliases: ['inventory:remove'],
    description: 'Stop keeping a Pokémon, by the number "inventory:list" shows',
)]
final class InventoryRemoveCommand extends Command
{
    public function __construct(
        private readonly RemoveFromInventoryHandler $handler,
        private readonly SpeciesNameResolver $names,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this->addArgument(
            'id',
            InputArgument::REQUIRED,
            'Number of the entry, as "inventory:list" shows it',
        )->addOption('locale', null, InputOption::VALUE_REQUIRED, 'Language of species names: fr or en');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $id = JsonValue::asString($input->getArgument('id')) ?? '';

        $number = ltrim(trim($id), '#');

        if (!ctype_digit($number)) {
            $io->error(\sprintf('Cannot read an entry number from "%s".', $id));

            return Command::INVALID;
        }

        try {
            $result = ($this->handler)(new RemoveFromInventoryCommand((int) $number));
        } catch (EntryNotFound $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success(\sprintf(
            'Removed #%d %s %s from the %s.',
            $result->entry->id,
            null === $result->species
                ? $result->entry->species->value
                : $this->names->name($result->species, JsonValue::asString($input->getOption('locale'))),
            $result->entry->iv,
            $result->entry->league->label(),
        ));

        return Command::SUCCESS;
    }
}
