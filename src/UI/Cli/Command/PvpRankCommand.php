<?php

declare(strict_types=1);

namespace App\UI\Cli\Command;

use App\Application\Pvp\RankPokemon\RankPokemonHandler;
use App\Domain\Pokemon\Exception\AmbiguousSpecies;
use App\Domain\Pokemon\Exception\InvalidIv;
use App\Domain\Pokemon\Exception\SpeciesNotFound;
use App\Domain\Pokemon\Model\Species;
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
    name: 'app:pvp:rank',
    aliases: ['pvp'],
    description: 'PvP ranking of a Pokémon and everything it evolves into, and where its IVs place',
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
            ->addOption('mega1', null, InputOption::VALUE_NONE, 'Rank the megas at Mega Level 1')
            ->addOption('mega2', null, InputOption::VALUE_NONE, 'Rank the megas at Mega Level 2')
            ->addOption('mega3', null, InputOption::VALUE_NONE, 'Rank the megas at Mega Level 3 (default)')
            ->addOption(
                'mega4',
                null,
                InputOption::VALUE_NONE,
                'Rank the megas at Mega Level 4 (Super Mega: +2 levels)',
            )
            ->addOption(
                'floor',
                null,
                InputOption::VALUE_REQUIRED,
                'Lowest IV the Pokémon can have, as a number or a source: wild, weather, good, great,'
                . ' ultra, best (friend trades), shadow, giovanni, raid, egg, research, lucky',
                'wild',
            )
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

                  <info>%command.full_name% ectoplasma</info>            Gengar, then Mega Gengar in the Mega Editions
                  <info>%command.full_name% 94 1/15/14</info>            + where those IVs place among the 4096
                  <info>%command.full_name% ralts 1/15/14</info>         Ralts and all it becomes, with the CP to stop at
                  <info>%command.full_name% gengar --league=great</info> a single league, megas left out
                  <info>%command.full_name% azurill --floor=best</info>  the best a Best Friend trade can give
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

            return Command::FAILURE;
        }

        // No explicit choice: let the language of the question decide the language of
        // the answer.
        $locale = JsonValue::asString($input->getOption('locale')) ?? $this->inputLocale->detect(
            $query->identifier,
            $result->asked,
        );

        'json' === $input->getOption('format')
            ? $output->writeln($this->json->present($result, $locale))
            : $this->text->present($io, $result, $locale);

        return Command::SUCCESS;
    }
}
