<?php

declare(strict_types=1);

namespace App\UI\Cli\Command;

use App\Application\Inventory\AddToInventory\AddedEntry;
use App\Application\Inventory\AddToInventory\AddToInventoryCommand;
use App\Application\Inventory\AddToInventory\AddToInventoryHandler;
use App\Application\Inventory\AddToInventory\AddToInventoryResult;
use App\Domain\Inventory\Exception\DuplicateEntry;
use App\Domain\Pokemon\Exception\AmbiguousSpecies;
use App\Domain\Pokemon\Exception\InvalidIv;
use App\Domain\Pokemon\Exception\SpeciesNotFound;
use App\Domain\Pokemon\Model\IvPattern;
use App\Domain\Pokemon\Model\Species;
use App\Domain\Pvp\Exception\IvMismatch;
use App\Domain\Pvp\Model\IvDeduction;
use App\Infrastructure\Json\JsonValue;
use App\Infrastructure\Naming\InputLocale;
use App\Infrastructure\Naming\SpeciesNameResolver;
use App\UI\Cli\Input\CpOption;
use App\UI\Cli\Input\LeagueOption;
use App\UI\Cli\Presenter\IvDeductionFormatter;
use App\UI\Cli\Presenter\StandingFormatter;
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
    description: 'Keep a Pokémon for a league, and see whether you already keep better',
)]
final class InventoryAddCommand extends Command
{
    public function __construct(
        private readonly AddToInventoryHandler $handler,
        private readonly SpeciesNameResolver $names,
        private readonly InputLocale $inputLocale,
        private readonly IvDeductionFormatter $deductions,
        private readonly StandingFormatter $standings,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addArgument('pokemon', InputArgument::REQUIRED, 'French name, English name or Pokédex number')
            ->addArgument(
                'ivs',
                InputArgument::REQUIRED,
                'IVs as attack/defense/stamina, e.g. 1/15/14; "x" for one to deduce from --cp',
            )
            ->addOption(
                'league',
                'l',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'League to keep it for (repeatable): great, ultra, master, mega-great, mega-ultra, mega-master',
            )
            ->addOption('shadow', 's', InputOption::VALUE_NONE, 'Shadow form')
            ->addOption(
                'cp',
                null,
                InputOption::VALUE_REQUIRED,
                'CP shown in the game: refuses IVs that cannot give it, settles those typed "x"',
            )
            ->addOption('locale', null, InputOption::VALUE_REQUIRED, 'Language of species names: fr or en')
            ->setHelp(<<<'HELP'
                Keeps the Pokémon in hand for each league given. It is ranked as the form of
                its family that league ranks best — a Dratini kept for the Great League counts
                as a Dragonair — and compared with what you already keep there.

                  <info>%command.full_name% gengar 1/15/14 -l great</info>
                  <info>%command.full_name% minidraco 0/14/15 -l great -l ultra</info>
                  <info>%command.full_name% 94 1/x/14 --cp=1478 -l mega-great</info>
                HELP);
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $identifier = JsonValue::asString($input->getArgument('pokemon')) ?? '';

        try {
            $leagues = LeagueOption::parse($input->getOption('league'));

            if ([] === $leagues) {
                throw new \InvalidArgumentException('Say which league to keep it for: --league=great, for one.');
            }

            $result = ($this->handler)(
                new AddToInventoryCommand(
                    $identifier,
                    IvPattern::fromString(JsonValue::asString($input->getArgument('ivs')) ?? ''),
                    $leagues,
                    true === $input->getOption('shadow'),
                    CpOption::parse(JsonValue::asString($input->getOption('cp'))),
                ),
            );
        } catch (IvMismatch $e) {
            $io->error($e->getMessage());

            if ([] !== $e->candidates) {
                $io->listing(array_map($this->deductions->candidate(...), $e->candidates));
            }

            return Command::INVALID;
        } catch (InvalidIv|\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::INVALID;
        } catch (DuplicateEntry $e) {
            $io->warning(\sprintf(
                'Already kept for the %s as #%d; nothing added.',
                $e->existing->league->label(),
                $e->existing->id,
            ));

            return Command::FAILURE;
        } catch (SpeciesNotFound $e) {
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

        $locale = JsonValue::asString($input->getOption('locale')) ?? $this->inputLocale->detect(
            $identifier,
            $result->species,
        );

        $this->present($io, $result, $locale);

        return Command::SUCCESS;
    }

    private function present(SymfonyStyle $io, AddToInventoryResult $result, string $locale): void
    {
        $name = $this->names->name($result->species, $locale);

        if (null !== $result->guessedFrom) {
            $io->text(\sprintf('<comment>Interpreted "%s" as %s.</comment>', $result->guessedFrom, $name));
        }

        if ($result->deduction instanceof IvDeduction) {
            $io->text(\sprintf('<comment>%s</comment>', $this->deductions->summary($result->deduction)));
        }

        foreach ($result->added as $added) {
            $io->newLine();
            $io->text($this->lines($added, $name, (string) $result->iv, $locale));
        }

        $io->newLine();
    }

    /**
     * @return list<string>
     */
    private function lines(AddedEntry $added, string $name, string $iv, string $locale): array
    {
        $standing = $added->standing;
        $lines = [
            \sprintf(
                '<info>Kept #%d</info> %s %s for the <options=bold>%s</>.',
                $added->entry->id,
                $name,
                $iv,
                $added->entry->league->label(),
            ),
            \sprintf(
                '  As %s: %s in the league — IVs %s',
                $this->standings->form($standing, $locale),
                $this->standings->speciesRank($standing->speciesRank),
                $this->standings->spread($standing->spread),
            ),
        ];

        if (null === $standing->spread) {
            return $lines;
        }

        $better = $added->better;
        $lines[] = null === $better
            ? '  <fg=green>Your best for that form in this league.</>'
            : \sprintf(
                '  <comment>You already keep better: #%d %s %s, IVs #%d as %s.</comment>',
                $better->entry->id,
                $this->names->name($better->species, $locale),
                $better->entry->iv,
                $better->spread->position,
                $this->standings->form($standing, $locale),
            );

        return $lines;
    }
}
