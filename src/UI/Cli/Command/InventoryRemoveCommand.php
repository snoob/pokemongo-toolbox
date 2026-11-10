<?php

declare(strict_types=1);

namespace App\UI\Cli\Command;

use App\Application\Inventory\ListInventory\InventoryLine;
use App\Application\Inventory\ListInventory\ListInventoryHandler;
use App\Application\Inventory\ListInventory\ListInventoryQuery;
use App\Application\Inventory\ListInventory\ListInventoryResult;
use App\Application\Inventory\RemoveFromInventory\RemoveFromInventoryCommand;
use App\Application\Inventory\RemoveFromInventory\RemoveFromInventoryHandler;
use App\Domain\Inventory\Exception\EntryNotFound;
use App\Domain\Inventory\Model\Account;
use App\Domain\Pokemon\Exception\AmbiguousSpecies;
use App\Domain\Pokemon\Exception\InvalidIv;
use App\Domain\Pokemon\Exception\SpeciesNotFound;
use App\Domain\Pokemon\Model\Species;
use App\Domain\Pvp\Exception\RankOutOfRange;
use App\Infrastructure\Json\JsonValue;
use App\Infrastructure\Naming\SpeciesNameResolver;
use App\UI\Cli\Input\AccountOption;
use App\UI\Cli\Input\KeptPokemonPicker;
use App\UI\Cli\Input\LeagueOption;
use App\UI\Cli\Input\PokemonCompletion;
use App\UI\Cli\Input\SpreadOrRankArgument;
use App\UI\Cli\Presenter\InventoryAddTextPresenter;
use App\UI\Cli\Presenter\InventoryOrder;
use App\UI\Cli\Presenter\KeptPokemonLabel;
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
    description: 'Stop keeping a Pokémon, named as "inventory:add" takes it',
)]
final class InventoryRemoveCommand extends Command
{
    public function __construct(
        private readonly ListInventoryHandler $finder,
        private readonly RemoveFromInventoryHandler $handler,
        private readonly InventoryOrder $order,
        private readonly SpeciesNameResolver $names,
        private readonly KeptPokemonLabel $labels,
        private readonly InventoryAddTextPresenter $refusals,
        private readonly PokemonCompletion $completion,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addArgument(
                'pokemon',
                InputArgument::OPTIONAL,
                'French name, English name or Pokédex number; left out, pick among everything kept',
                suggestedValues: $this->completion->suggest(...),
            )
            ->addArgument(
                'league',
                InputArgument::OPTIONAL,
                'League it is kept for: ' . implode(', ', LeagueOption::names()),
                suggestedValues: LeagueOption::names(),
            )
            ->addArgument(
                'ivs|rank',
                InputArgument::OPTIONAL,
                'IVs, e.g. 1/15/14, or their rank in the league, as "inventory:add" reads them',
            )
            ->addOption('shadow', 's', InputOption::VALUE_NONE, 'Only the shadow form')
            ->addOption('locale', null, InputOption::VALUE_REQUIRED, 'Language of species names: fr or en')
            ->setHelp(<<<'HELP'
                Names the Pokémon as <info>inventory:add</info> does. Each argument narrows the
                search: when one kept Pokémon is left, it is removed; when several are, pick
                one with the arrows. A name alone matches its shadow form too.

                  <info>%command.full_name% minidraco great 0/15/15</info>
                  <info>%command.full_name% minidraco great 6</info>
                  <info>%command.full_name% minidraco</info>
                  <info>%command.full_name%</info>
                HELP);
        AccountOption::addTo($this);
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $pokemon = JsonValue::asString($input->getArgument('pokemon'));
        $league = JsonValue::asString($input->getArgument('league'));
        $ivs = JsonValue::asString($input->getArgument('ivs|rank'));
        $locale = JsonValue::asString($input->getOption('locale'));

        try {
            $account = AccountOption::parse($input);
            $found = $this->order->sorted(
                ($this->finder)(
                    new ListInventoryQuery(
                        null === $league ? null : LeagueOption::one($league),
                        $account,
                        $pokemon,
                        true === $input->getOption('shadow'),
                        null === $ivs ? null : SpreadOrRankArgument::parse($ivs),
                    ),
                ),
                $locale,
            );
        } catch (InvalidIv|\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::INVALID;
        } catch (RankOutOfRange $e) {
            $io->error($this->refusals->refusal($e, $locale));

            return Command::FAILURE;
        } catch (SpeciesNotFound $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        } catch (AmbiguousSpecies $e) {
            $io->error(\sprintf('"%s" matches several forms.', $e->identifier));
            $io->listing(array_map(fn(Species $s): string => \sprintf(
                '%s (%s)',
                $this->names->name($s, $locale),
                $s->id->value,
            ), $e->candidates));

            return Command::FAILURE;
        }

        return $this->remove($input, $io, $found, $account, $locale);
    }

    private function remove(
        InputInterface $input,
        SymfonyStyle $io,
        ListInventoryResult $found,
        Account $account,
        ?string $locale,
    ): int {
        if (null !== $found->guessedFrom && null !== $found->species) {
            $io->text(\sprintf(
                '<comment>Interpreted "%s" as %s.</comment>',
                $found->guessedFrom,
                $this->names->name($found->species, $locale),
            ));
        }

        $lines = $found->lines;

        if ([] === $lines) {
            $io->error(\sprintf('Nothing kept matches (%s); "inventory:list" shows what is.', $account));

            return Command::FAILURE;
        }

        $label = fn(InventoryLine $line): string => $this->labels->of($line, $locale);

        if (\count($lines) > 1 && !$input->isInteractive()) {
            $io->error(\sprintf('%d kept Pokémon match; say which:', \count($lines)));
            $io->listing(array_map($label, $lines));

            return Command::FAILURE;
        }

        $line = 1 === \count($lines)
            ? $lines[0]
            : KeptPokemonPicker::pick($input, $io, 'Which one should go?', $lines, $label);

        try {
            ($this->handler)(new RemoveFromInventoryCommand($line->entry->id, $account));
        } catch (EntryNotFound $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success(\sprintf('Removed %s (%s).', $this->labels->of($line, $locale), $account));

        return Command::SUCCESS;
    }
}
