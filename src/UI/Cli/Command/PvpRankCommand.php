<?php

declare(strict_types=1);

namespace App\UI\Cli\Command;

use App\Application\Pvp\RankPokemon\RankPokemonHandler;
use App\Application\Pvp\RankPokemon\RankPokemonQuery;
use App\Domain\Pokemon\Exception\AmbiguousSpecies;
use App\Domain\Pokemon\Exception\InvalidIv;
use App\Domain\Pokemon\Exception\SpeciesNotFound;
use App\Domain\Pokemon\Model\IvSpread;
use App\Domain\Pokemon\Model\Species;
use App\Domain\Pvp\Model\League;
use App\Domain\Pvp\Model\PokemonLevel;
use App\Infrastructure\Json\JsonValue;
use App\Infrastructure\Naming\SpeciesNameResolver;
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
    description: "Classement PvP d'un Pokémon par ligue, et rang de ses IVs au sein de l'espèce",
)]
final class PvpRankCommand extends Command
{
    public function __construct(
        private readonly RankPokemonHandler $handler,
        private readonly PvpRankTextPresenter $text,
        private readonly PvpRankJsonPresenter $json,
        private readonly SpeciesNameResolver $names,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addArgument('pokemon', InputArgument::REQUIRED, 'Nom français, nom anglais ou numéro de Pokédex')
            ->addArgument('ivs', InputArgument::OPTIONAL, 'IVs sous la forme attaque/défense/endurance, ex. 1/15/14')
            ->addOption(
                'league',
                'l',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Ligue à afficher : great, ultra ou master (toutes par défaut)',
            )
            ->addOption('shadow', null, InputOption::VALUE_NONE, 'Utiliser la forme obscure')
            ->addOption('best-buddy', 'b', InputOption::VALUE_NONE, 'Autoriser le niveau 51 (Meilleur Copain)')
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Format de sortie : text ou json', 'text')
            ->addOption('locale', null, InputOption::VALUE_REQUIRED, "Langue d'affichage : fr ou en")
            ->setHelp(<<<'HELP'
                Exemples :

                  <info>%command.full_name% ectoplasma</info>            rang de l'espèce dans les trois ligues
                  <info>%command.full_name% 94 1/15/14</info>            + rang de ces IVs parmi les 4096 possibles
                  <info>%command.full_name% gengar --league=great</info> une seule ligue
                HELP);
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $result = ($this->handler)($this->buildQuery($input));
        } catch (InvalidIv|\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::INVALID;
        } catch (SpeciesNotFound $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        } catch (AmbiguousSpecies $e) {
            $io->error(\sprintf('"%s" correspond à plusieurs formes.', $e->identifier));
            $locale = JsonValue::asString($input->getOption('locale'));

            $io->listing(array_map(fn(Species $s): string => \sprintf(
                '%s (%s)',
                $this->names->name($s, $locale),
                $s->id->value,
            ), $e->candidates));

            return Command::FAILURE;
        }

        $locale = JsonValue::asString($input->getOption('locale'));

        'json' === $input->getOption('format')
            ? $output->writeln($this->json->present($result, $locale))
            : $this->text->present($io, $result, $locale);

        return Command::SUCCESS;
    }

    private function buildQuery(InputInterface $input): RankPokemonQuery
    {
        $pokemon = JsonValue::asString($input->getArgument('pokemon'));
        $ivs = JsonValue::asString($input->getArgument('ivs'));

        if (null === $pokemon) {
            throw new \InvalidArgumentException('Il faut un Pokémon à classer.');
        }

        return new RankPokemonQuery(
            identifier: $pokemon,
            iv: null === $ivs ? null : IvSpread::fromString($ivs),
            leagues: $this->parseLeagues($input->getOption('league')),
            levelCap: true === $input->getOption('best-buddy')
                ? PokemonLevel::bestBuddyCap()
                : PokemonLevel::regularCap(),
            shadow: true === $input->getOption('shadow'),
        );
    }

    /**
     * @return non-empty-list<League>
     */
    private function parseLeagues(mixed $option): array
    {
        if (!\is_array($option) || [] === $option) {
            return League::all();
        }

        $leagues = [];

        foreach (JsonValue::asStringList($option) as $value) {
            $league = League::tryFrom(strtolower($value));

            if (null === $league) {
                throw new \InvalidArgumentException(\sprintf(
                    'Ligue inconnue "%s" ; attendu : great, ultra ou master.',
                    $value,
                ));
            }

            $leagues[] = $league;
        }

        // An option array that held nothing usable means "no filter", not "no league".
        return [] === $leagues ? League::all() : $leagues;
    }
}
