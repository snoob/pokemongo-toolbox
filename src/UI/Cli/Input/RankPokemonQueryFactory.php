<?php

declare(strict_types=1);

namespace App\UI\Cli\Input;

use App\Application\Pvp\RankPokemon\RankPokemonQuery;
use App\Domain\Pokemon\Model\IvPattern;
use App\Domain\Pvp\Model\Cp;
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
            iv: null === $ivs ? null : IvPattern::fromString($ivs),
            leagues: LeagueOption::parse($input->getOption('league')),
            levelCap: true === $input->getOption('best-buddy')
                ? PokemonLevel::bestBuddyCap()
                : PokemonLevel::regularCap(),
            shadow: true === $input->getOption('shadow'),
            megaLevel: $this->parseMegaLevel($input),
            floor: FloorOption::parse($input),
            cp: $this->parseCp(JsonValue::asString($input->getOption('cp')), $ivs),
        );
    }

    /**
     * The CP is checked against IVs: on its own it would say nothing about the Pokémon.
     */
    private function parseCp(?string $value, ?string $ivs): ?Cp
    {
        if (null === $value) {
            return null;
        }

        if (null === $ivs) {
            throw new \InvalidArgumentException('--cp checks IVs: give them too, with "x" for those to deduce.');
        }

        return CpOption::parse($value);
    }

    /**
     * Megas are always ranked; these flags only pick the Mega Level they are ranked at.
     * "--mega4" is the Super Mega tier, the only one that shifts the CP to stop at.
     */
    private function parseMegaLevel(InputInterface $input): ?MegaLevel
    {
        for ($level = MegaLevel::MIN; $level <= MegaLevel::MAX; ++$level) {
            if (true === $input->getOption('mega' . $level)) {
                return new MegaLevel($level);
            }
        }

        return null;
    }
}
