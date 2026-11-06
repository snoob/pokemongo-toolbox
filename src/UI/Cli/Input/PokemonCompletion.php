<?php

declare(strict_types=1);

namespace App\UI\Cli\Input;

use App\Domain\Pokemon\Port\SpeciesCatalog;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\Suggestion;

/**
 * Completes the `pokemon` argument in the shell (`pogo completion zsh`): `pogo pvp ecto`
 * then Tab offers `ectoplasma`, described as Ectoplasma.
 *
 * The completed word is the folded name rather than the name itself: the shell keeps
 * only what begins with the letters typed, so "melo" would never reach "Mélodelfe", and
 * a name with a space or an apostrophe — Raichu (d'Alola) — would need quoting. The
 * folded name resolves like the full one, and the answer speaks its language.
 */
final readonly class PokemonCompletion
{
    public function __construct(
        private SpeciesCatalog $catalog,
    ) {}

    /**
     * @return list<Suggestion>
     */
    public function suggest(CompletionInput $input): array
    {
        $suggestions = [];

        foreach ($this->catalog->namesStartingWith($input->getCompletionValue()) as $typed => $name) {
            $suggestions[] = new Suggestion($typed, $name);
        }

        return $suggestions;
    }
}
