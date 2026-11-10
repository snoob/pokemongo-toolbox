<?php

declare(strict_types=1);

namespace App\UI\Cli\Command;

use App\Application\Inventory\CheckInventory\CheckedPokemon;
use App\Application\Inventory\CheckInventory\CheckInventoryHandler;
use App\Application\Inventory\CheckInventory\CheckInventoryQuery;
use App\Domain\Pokemon\Exception\InvalidIv;
use App\Infrastructure\Json\JsonValue;
use App\UI\Cli\Input\AccountOption;
use App\UI\Cli\Input\FloorOption;
use App\UI\Cli\Input\LeagueOption;
use App\UI\Cli\Presenter\InventoryCheckTextPresenter;
use App\UI\Cli\Presenter\InventoryJsonPresenter;
use App\UI\Cli\Presenter\InventoryOrder;
use App\UI\Cli\Settings\AccountHint;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:inventory:check',
    aliases: ['inventory:check'],
    description: 'Tell which kept Pokémon a trade can still improve, and how likely',
)]
final class InventoryCheckCommand extends Command
{
    public function __construct(
        private readonly CheckInventoryHandler $handler,
        private readonly InventoryOrder $order,
        private readonly InventoryCheckTextPresenter $text,
        private readonly InventoryJsonPresenter $json,
        private readonly AccountHint $hint,
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
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'League (repeatable): great, ultra, master, mega-great, mega-ultra, mega-master',
            )
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Output format: text or json', 'text')
            ->addOption('locale', null, InputOption::VALUE_REQUIRED, 'Language of species names: fr or en')
            ->setHelp(<<<'HELP'
                Weighs each kept Pokémon, as the form its league ranks best, against every
                spread a trade under the floor can roll: each is as likely, so the share that
                beats it is the chance one trade does better. Those no trade can beat are done,
                a wild catch under the floor included.

                The floor and the leagues default to the "pvp.floor" and "pvp.league"
                settings the wizard asks for.

                  <info>%command.full_name%</info>
                  <info>%command.full_name% -l great --floor=5</info>
                HELP);
        FloorOption::addTo($this);
        AccountOption::addTo($this);
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $locale = JsonValue::asString($input->getOption('locale'));

        try {
            $result = ($this->handler)(
                new CheckInventoryQuery(
                    FloorOption::parse($input),
                    LeagueOption::parse($input->getOption('league')),
                    AccountOption::parse($input),
                ),
            );
        } catch (InvalidIv|\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::INVALID;
        }

        $checked = $result->checked;
        usort($checked, fn(CheckedPokemon $a, CheckedPokemon $b): int => $this->order->compare(
            $a->line,
            $b->line,
            $locale,
        ));

        if ('json' === $input->getOption('format')) {
            $output->writeln($this->toJson($checked, (string) $result->account, $locale));

            return Command::SUCCESS;
        }

        $this->text->present($io, $result, $checked, $locale);
        $this->hint->show($input, $io);

        return Command::SUCCESS;
    }

    /**
     * @param list<CheckedPokemon> $checked
     */
    private function toJson(array $checked, string $account, ?string $locale): string
    {
        return json_encode(
            array_map(
                /** @return array<string, mixed> */
                fn(CheckedPokemon $c): array => [
                    'account' => $account,
                    ...$this->json->line($c->line, $locale),
                    'beatenWithinFloor' => $c->odds?->beatenBy,
                    'spreadsWithinFloor' => $c->odds?->total,
                    'bestWithinFloor' => null === $c->odds
                        ? null
                        : ['iv' => (string) $c->odds->best->iv, 'position' => $c->odds->best->position],
                ],
                $checked,
            ),
            \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR,
        );
    }
}
