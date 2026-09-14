<?php

declare(strict_types=1);

namespace App\Domain\Pokemon\Exception;

use App\Domain\Pokemon\Model\Species;

final class AmbiguousSpecies extends \RuntimeException
{
    /**
     * @param non-empty-list<Species> $candidates
     */
    public function __construct(
        public readonly string $identifier,
        public readonly array $candidates,
    ) {
        parent::__construct(\sprintf(
            '"%s" matches %d species; pick one of: %s.',
            $identifier,
            \count($candidates),
            implode(', ', array_map(static fn(Species $s): string => $s->id->value, $candidates)),
        ));
    }
}
