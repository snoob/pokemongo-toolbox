<?php

declare(strict_types=1);

namespace App\Infrastructure\Naming;

use App\Domain\Pokemon\Model\MoveId;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The display name of a move, from the catalogue generated at build time. An untranslated
 * move falls back to English rather than showing its raw identifier.
 */
final readonly class MoveNameResolver
{
    public const string DOMAIN = 'pokemon_move';

    public function __construct(
        private TranslatorInterface $translator,
    ) {}

    public function name(MoveId $move, ?string $locale = null): string
    {
        return $this->translator->trans($move->value, domain: self::DOMAIN, locale: $locale);
    }
}
