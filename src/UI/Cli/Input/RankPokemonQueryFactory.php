<?php

declare(strict_types=1);

namespace App\UI\Cli\Input;

use App\Application\Pvp\RankPokemon\RankPokemonQuery;
use App\Domain\Pokemon\Model\IvSpread;
use App\Domain\Pvp\Model\League;
use App\Domain\Pvp\Model\MegaLevel;
use App\Domain\Pvp\Model\PokemonLevel;
use App\Infrastructure\Json\JsonValue;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Turns the command line into a query. Kept apart from the command so that adding an
 * option does not grow the class that also has to render errors and results.
 */
final readonly class RankPokemonQueryFactory
{
    public function from(InputInterface $input): RankPokemonQuery
    {
        $pokemon = JsonValue::asString($input->getArgument('pokemon'));
        $ivs = JsonValue::asString($input->getArgument('ivs'));

        if (null === $pokemon) {
            throw new \InvalidArgumentException('A Pokémon to rank is required.');
        }

        return new RankPokemonQuery(
            identifier: $pokemon,
            iv: null === $ivs ? null : IvSpread::fromString($ivs),
            leagues: $this->parseLeagues($input->getOption('league')),
            levelCap: true === $input->getOption('best-buddy')
                ? PokemonLevel::bestBuddyCap()
                : PokemonLevel::regularCap(),
            shadow: true === $input->getOption('shadow'),
            megaLevel: $this->parseMegaLevel($input),
            megaVariant: $this->parseMegaVariant($input),
        );
    }

    /**
     * A bare "--mega" means the level a PvP player targets; "--mega4" is the Super Mega
     * tier, the only one that shifts the CP to stop at. Asking for a variant implies a
     * mega too, since only megas have one.
     */
    private function parseMegaLevel(InputInterface $input): ?MegaLevel
    {
        for ($level = MegaLevel::MIN; $level <= MegaLevel::MAX; ++$level) {
            if (true === $input->getOption('mega' . $level)) {
                return new MegaLevel($level);
            }
        }

        return true === $input->getOption('mega') || null !== $this->parseMegaVariant($input)
            ? MegaLevel::default()
            : null;
    }

    private function parseMegaVariant(InputInterface $input): ?string
    {
        foreach (['x', 'y'] as $variant) {
            if (true === $input->getOption('mega-' . $variant)) {
                return $variant;
            }
        }

        return null;
    }

    /**
     * @return list<League> empty when the caller did not choose, letting the species decide
     */
    private function parseLeagues(mixed $option): array
    {
        if (!\is_array($option) || [] === $option) {
            return [];
        }

        $leagues = [];

        foreach (JsonValue::asStringList($option) as $value) {
            $league = League::tryFrom(strtolower($value));

            if (null === $league) {
                throw new \InvalidArgumentException(\sprintf(
                    'Unknown league "%s"; expected great, ultra, master, mega-great, mega-ultra or mega-master.',
                    $value,
                ));
            }

            $leagues[] = $league;
        }

        return $leagues;
    }
}
