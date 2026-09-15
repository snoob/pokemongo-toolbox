<?php

declare(strict_types=1);

namespace App\Domain\Pokemon\Exception;

final class SpeciesNotFound extends \RuntimeException
{
    public static function withoutMegaForm(string $identifier): self
    {
        return new self(\sprintf('"%s" has no Mega Evolution.', $identifier));
    }

    public static function forIdentifier(string $identifier): self
    {
        return new self(\sprintf(
            'No species matches "%s" (try an English name, a French name or a Pokédex number).',
            $identifier,
        ));
    }
}
