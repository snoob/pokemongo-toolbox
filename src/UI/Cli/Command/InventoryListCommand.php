<?php

declare(strict_types=1);

namespace App\UI\Cli\Command;

use App\Application\Inventory\ListInventory\ListInventoryHandler;
use App\Application\Inventory\ListInventory\ListInventoryQuery;
use App\Infrastructure\Json\JsonValue;
use App\UI\Cli\Input\AccountOption;
use App\UI\Cli\Input\LeagueOption;
use App\UI\Cli\Presenter\InventoryJsonPresenter;
use App\UI\Cli\Presenter\InventoryOrder;
use App\UI\Cli\Presenter\InventoryTextPresenter;
use App\UI\Cli\Settings\AccountHint;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:inventory:list',
    aliases: ['inventory', 'inventory:list'],
    description: 'The Pokémon you keep, league by league, best first',
)]
final class InventoryListCommand extends Command
{
    public function __construct(
        private readonly ListInventoryHandler $handler,
        private readonly InventoryTextPresenter $text,
        private readonly InventoryJsonPresenter $json,
        private readonly AccountHint $hint,
        private readonly InventoryOrder $order,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addOption(
                'league',
                'l',
                InputOption::VALUE_REQUIRED,
                'Only this league: great, ultra, master, mega-great, mega-ultra, mega-master',
            )
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Output format: text or json', 'text')
            ->addOption('locale', null, InputOption::VALUE_REQUIRED, 'Language of species names: fr or en');
        AccountOption::addTo($this);
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $league = JsonValue::asString($input->getOption('league'));

        try {
            $leagues = null === $league ? [] : LeagueOption::parse([$league]);
            $account = AccountOption::parse($input);
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::INVALID;
        }

        $locale = JsonValue::asString($input->getOption('locale'));
        $result = $this->order->sorted(
            ($this->handler)(new ListInventoryQuery($leagues[0] ?? null, $account)),
            $locale,
        );

        if ('json' === $input->getOption('format')) {
            $output->writeln($this->json->present($result, $locale));

            return Command::SUCCESS;
        }

        $this->text->present($io, $result, $locale);
        $this->hint->show($input, $io);

        return Command::SUCCESS;
    }
}
