<?php

declare(strict_types=1);

namespace App\Domain\Pvp\Exception;

use App\Domain\Pokemon\Model\IvPattern;
use App\Domain\Pvp\Model\Cp;
use App\Domain\Pvp\Model\IvDeduction;

/**
 * The CP read in the game does not settle the IVs: either no spread gives it, which
 * means a misread, or several do, which means one more IV is needed.
 */
final class IvMismatch extends \DomainException
{
    /**
     * @param list<IvDeduction> $candidates empty when nothing gives that CP
     */
    private function __construct(
        string $message,
        public readonly array $candidates = [],
    ) {
        parent::__construct($message);
    }

    public static function none(IvPattern $pattern, Cp $cp): self
    {
        return new self(\sprintf('No level gives CP %d with IVs %s; check the IVs and the CP.', $cp->value, $pattern));
    }

    /**
     * @param list<IvDeduction> $candidates
     */
    public static function ambiguous(IvPattern $pattern, Cp $cp, array $candidates): self
    {
        return new self(
            \sprintf('CP %d with IVs %s fits %d spreads; give one more IV.', $cp->value, $pattern, \count($candidates)),
            $candidates,
        );
    }
}
