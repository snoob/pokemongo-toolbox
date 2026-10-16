<?php

declare(strict_types=1);

namespace App\Domain\Pokemon\Model;

/**
 * The forms a Pokémon goes through from the one in hand to the one that fights:
 * Ralts → Kirlia → Gardevoir → Mega Gardevoir. A single stage means it stays as it is.
 *
 * Evolving and Mega Evolving both keep the IVs and the level, so the first stage's CP at
 * the level the last one is ranked at is the figure to power up to.
 */
final readonly class EvolutionPath
{
    /**
     * @param non-empty-list<Species> $stages
     */
    public function __construct(
        public array $stages,
    ) {}

    public function first(): Species
    {
        return $this->stages[0];
    }

    public function last(): Species
    {
        return array_last($this->stages);
    }

    public function isEvolution(): bool
    {
        return \count($this->stages) > 1;
    }

    /**
     * One step from a form to its mega: the only case where the CP gain on evolving is
     * worth showing. From further back it would mostly measure the ordinary evolutions.
     */
    public function isMegaEvolution(): bool
    {
        return 2 === \count($this->stages) && $this->last()->isMega();
    }

    /**
     * Whether the other path goes through this one and further: Ralts → Kirlia leads to
     * Ralts → Kirlia → Gardevoir.
     */
    public function leadsTo(self $other): bool
    {
        $length = \count($this->stages);

        return (
            \count($other->stages) > $length
            && self::ids($this->stages) === self::ids(\array_slice($other->stages, 0, $length))
        );
    }

    /**
     * @param list<Species> $stages
     *
     * @return list<string>
     */
    private static function ids(array $stages): array
    {
        return array_map(static fn(Species $stage): string => $stage->id->value, $stages);
    }

    public function then(Species $next): self
    {
        return new self([...$this->stages, $next]);
    }
}
