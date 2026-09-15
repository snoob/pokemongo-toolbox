<?php

declare(strict_types=1);

namespace App\UI\Cli\Command;

use App\Application\Pvp\RankPokemon\RankPokemonHandler;
use App\Domain\Pokemon\Exception\AmbiguousSpecies;
use App\Domain\Pokemon\Exception\InvalidIv;
use App\Domain\Pokemon\Exception\SpeciesNotFound;
use App\Domain\Pokemon\Model\Species;
use App\Domain\Pvp\Model\MegaLevel;
use App\Infrastructure\Json\JsonValue;
use App\Infrastructure\Naming\InputLocale;
use App\Infrastructure\Naming\SpeciesNameResolver;
use App\UI\Cli\Input\RankPokemonQueryFactory;
use App\UI\Cli\Presenter\PvpRankJsonPresenter;
use App\UI\Cli\Presenter\PvpRankTextPresenter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'pogo:pvp:rank',
    description: 'PvP ranking of a Pokémon per league, and where its IVs place within the species',
)]
final class PvpRankCommand extends Command
{
    public function __construct(
        private readonly RankPokemonHandler $handler,
        private readonly PvpRankTextPresenter $text,
        private readonly PvpRankJsonPresenter $json,
        private readonly SpeciesNameResolver $names,
        private readonly InputLocale $inputLocale,
        private readonly RankPokemonQueryFactory $queries,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addArgument('pokemon', InputArgument::REQUIRED, 'French name, English name or Pokédex number')
            ->addArgument('ivs', InputArgument::OPTIONAL, 'IVs as attack/defense/stamina, e.g. 1/15/14')
            ->addOption(
                'league',
                'l',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'League: great, ultra, master, mega-great, mega-ultra, mega-master'
                . ' (defaults to the three matching the species)',
            )
            ->addOption('shadow', null, InputOption::VALUE_NONE, 'Use the shadow form')
            ->addOption('mega', 'm', InputOption::VALUE_NONE, 'Mega Evolution (level ' . MegaLevel::DEFAULT . ')')
            ->addOption('mega1', null, InputOption::VALUE_NONE, 'Mega Evolution at level 1')
            ->addOption('mega2', null, InputOption::VALUE_NONE, 'Mega Evolution at level 2')
            ->addOption('mega3', null, InputOption::VALUE_NONE, 'Mega Evolution at level 3 (default)')
            ->addOption('mega4', null, InputOption::VALUE_NONE, 'Mega Evolution at level 4 (Super Mega: +2 levels)')
            ->addOption('mega-x', null, InputOption::VALUE_NONE, 'Mega X (Charizard, Raichu, Mewtwo)')
            ->addOption('mega-y', null, InputOption::VALUE_NONE, 'Mega Y (Charizard, Raichu, Mewtwo)')
            ->addOption('best-buddy', 'b', InputOption::VALUE_NONE, 'Allow level 51 (Best Buddy)')
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Output format: text or json', 'text')
            ->addOption(
                'locale',
                null,
                InputOption::VALUE_REQUIRED,
                'Language of Pokémon and move names: fr or en (defaults to the language you typed)',
            )
            ->setHelp(<<<'HELP'
                Examples:

                  <info>%command.full_name% ectoplasma</info>            species rank across the three leagues
                  <info>%command.full_name% 94 1/15/14</info>            + where those IVs place among the 4096
                  <info>%command.full_name% gengar --league=great</info> a single league
                  <info>%command.full_name% altaria --mega</info>        the Mega Editions
                HELP);
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $query = $this->queries->from($input);
            $result = ($this->handler)($query);
        } catch (InvalidIv|\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::INVALID;
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

            if ($this->areMegaVariants($e->candidates)) {
                $io->text('<comment>Narrow it down with <info>--mega-x</info> or <info>--mega-y</info>.</comment>');
            }

            return Command::FAILURE;
        }

        // No explicit choice: let the language of the question decide the language of
        // the answer.
        $locale = JsonValue::asString($input->getOption('locale')) ?? $this->inputLocale->detect(
            $query->identifier,
            $result->species,
        );

        'json' === $input->getOption('format')
            ? $output->writeln($this->json->present($result, $locale))
            : $this->text->present($io, $result, $locale);

        return Command::SUCCESS;
    }

    /**
     * @param list<Species> $candidates
     */
    private function areMegaVariants(array $candidates): bool
    {
        foreach ($candidates as $candidate) {
            if (!$candidate->hasMegaVariant('x') && !$candidate->hasMegaVariant('y')) {
                return false;
            }
        }

        return [] !== $candidates;
    }
}
