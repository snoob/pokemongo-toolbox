<?php

declare(strict_types=1);

namespace App\Domain\Stardust\Model;

final readonly class GymFeeding
{
    /**
     * @param non-empty-list<FedDefender> $defenders in the order they are fed
     */
    public function __construct(
        public array $defenders,
    ) {}

    public function berries(): int
    {
        return array_sum(array_map(static fn(FedDefender $defender): int => $defender->berries, $this->defenders));
    }

    public function stardust(): Stardust
    {
        return $this->stardustUpTo(\count($this->defenders));
    }

    /**
     * What a gym pays when only the first defenders are there to feed.
     */
    public function stardustUpTo(int $position): Stardust
    {
        $total = Stardust::none();

        foreach ($this->defenders as $defender) {
            if ($defender->position > $position) {
                continue;
            }

            $total = $total->plus($defender->stardust);
        }

        return $total;
    }
}
