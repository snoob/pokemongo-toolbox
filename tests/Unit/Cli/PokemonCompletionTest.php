<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cli;

use App\Tests\Fake\InMemorySpeciesCatalog;
use App\UI\Cli\Input\PokemonCompletion;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\Suggestion;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;

final class PokemonCompletionTest extends TestCase
{
    public function testOffersTheWordToTypeDescribedByTheName(): void
    {
        $completion = new PokemonCompletion(
            new InMemorySpeciesCatalog([], [
                'gengar' => ['Ectoplasma', 'Gengar'],
                'haunter' => ['Spectrum', 'Haunter'],
            ]),
        );

        $input = CompletionInput::fromTokens(['pvp', 'ecto'], 1);
        $input->bind(new InputDefinition([new InputArgument('command'), new InputArgument('pokemon')]));
        $suggestions = $completion->suggest($input);

        self::assertSame(
            [['ectoplasma', 'Ectoplasma']],
            array_map(
                /** @return array{string, string} */
                static fn(Suggestion $s): array => [$s->getValue(), $s->getDescription()],
                $suggestions,
            ),
        );
    }
}
