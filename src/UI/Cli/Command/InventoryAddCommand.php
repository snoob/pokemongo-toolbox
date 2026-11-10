<?php

declare(strict_types=1);

namespace App\UI\Cli\Command;

use App\Application\Inventory\AddToInventory\AddToInventoryCommand;
use App\Application\Inventory\AddToInventory\AddToInventoryHandler;
use App\Domain\Inventory\Exception\DuplicateEntry;
use App\Domain\Pokemon\Exception\AmbiguousSpecies;
use App\Domain\Pokemon\Exception\InvalidIv;
use App\Domain\Pokemon\Exception\SpeciesNotFound;
use App\Domain\Pokemon\Model\Species;
use App\Domain\Pvp\Exception\EvolvesOverCap;
use App\Domain\Pvp\Exception\IvMismatch;
use App\Domain\Pvp\Exception\RankOutOfRange;
use App\Infrastructure\Json\JsonValue;
use App\Infrastructure\Naming\InputLocale;
use App\Infrastructure\Naming\SpeciesNameResolver;
use App\UI\Cli\Input\AccountOption;
use App\UI\Cli\Input\CpOption;
use App\UI\Cli\Input\LeagueOption;
use App\UI\Cli\Input\PokemonCompletion;
use App\UI\Cli\Input\SpreadOrRankArgument;
use App\UI\Cli\Presenter\InventoryAddTextPresenter;
use App\UI\Cli\Settings\AccountHint;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:inventory:add',
    aliases: ['inventory:add'],
    description: 'Keep a Pokémon for a league by its IVs or their rank, and see whether you already keep better',
)]
final class InventoryAddCommand extends Command
{
    public function __construct(
        private readonly AddToInventoryHandler $handler,
        private readonly SpeciesNameResolver $names,
        private readonly InputLocale $inputLocale,
        private readonly InventoryAddTextPresenter $presenter,
        private readonly AccountHint $hint,
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
                InputArgument::REQUIRED,
                'French name, English name or Pokédex number',
                suggestedValues: $this->completion->suggest(...),
            )
            ->addArgument(
                'league',
                InputArgument::REQUIRED,
                'League to keep it for: ' . implode(', ', LeagueOption::names()),
                suggestedValues: LeagueOption::names(),
            )
            ->addArgument(
                'ivs|rank',
                InputArgument::REQUIRED,
                'IVs as attack/defense/stamina, e.g. 1/15/14, or a number alone: their rank as the form ranked in the league, e.g. 6',
            )
            ->addOption(
                'cp',
                null,
                InputOption::VALUE_REQUIRED,
                'CP shown in the game: refuses a Pokémon powered up past what the league allows',
            )
            ->addOption('shadow', 's', InputOption::VALUE_NONE, 'Shadow form')
            ->addOption('locale', null, InputOption::VALUE_REQUIRED, 'Language of species names: fr or en')
            ->setHelp(<<<'HELP'
                Keeps the Pokémon in hand for a league. It is ranked as the form of its family
                that league ranks best — a Dratini kept for the Great League counts as a
                Dragonair — and compared with what you already keep there. To keep it for
                two leagues, add it twice.

                Give its three IVs, or a number alone: the rank of its IVs as that form, at
                level 50 — the rank <info>inventory:list</info> shows. With <info>--cp</info>, a Pokémon
                already powered up past what the league allows once evolved is refused.

                  <info>%command.full_name% gengar great 1/15/14</info>
                  <info>%command.full_name% minidraco great 6 --cp=815</info>
                  <info>%command.full_name% 94 mega-great 1/15/14 -a snoob2555</info>
                HELP);
        AccountOption::addTo($this);
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $identifier = JsonValue::asString($input->getArgument('pokemon')) ?? '';

        try {
            $result = ($this->handler)(
                new AddToInventoryCommand(
                    $identifier,
                    SpreadOrRankArgument::parse(JsonValue::asString($input->getArgument('ivs|rank')) ?? ''),
                    LeagueOption::one(JsonValue::asString($input->getArgument('league')) ?? ''),
                    CpOption::parse(JsonValue::asString($input->getOption('cp'))),
                    true === $input->getOption('shadow'),
                    AccountOption::parse($input),
                ),
            );
        } catch (InvalidIv|\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::INVALID;
        } catch (RankOutOfRange|EvolvesOverCap $e) {
            $io->error($this->presenter->refusal($e, $this->locale($input, $identifier, $e->form)));

            return Command::FAILURE;
        } catch (DuplicateEntry $e) {
            $io->warning(\sprintf(
                'Already kept for the %s as #%d; nothing added.',
                $e->existing->league->label(),
                $e->existing->id,
            ));

            return Command::FAILURE;
        } catch (SpeciesNotFound|IvMismatch $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        } catch (AmbiguousSpecies $e) {
            $io->error(\sprintf('"%s" matches several forms.', $e->identifier));
            $locale = JsonValue::asString($input->getOption('locale'));
            $io->listing(array_map(fn(Species $s): string => \sprintf(
                '%s (%s)',
                $this->names->name($s, $locale),
                $s->id->value,
            ), $e->candidates));

            return Command::FAILURE;
        }

        $locale = $this->locale($input, $identifier, $result->species);

        $this->presenter->present($io, $result, $locale);
        $this->hint->show($input, $io);

        return Command::SUCCESS;
    }

    private function locale(InputInterface $input, string $identifier, Species $species): string
    {
        return JsonValue::asString($input->getOption('locale')) ?? $this->inputLocale->detect($identifier, $species);
    }
}
