<?php

declare(strict_types=1);

namespace App\Domain\Pokemon\Model;

use App\Domain\Pokemon\Exception\InvalidIv;

/**
 * The lowest value each IV can take, set by where the Pokémon comes from: a trade rolls
 * its IVs again from a floor that rises with friendship, a raid never gives less than 10.
 * The floor applies to all three IVs at once.
 */
final readonly class IvFloor implements \Stringable
{
    /**
     * Floors the game guarantees, by source. "great" and "ultra" are friendship levels
     * here, not leagues: they only ever follow "--floor". A shadow comes from a shadow
     * raid or from Giovanni, both at 6.
     *
     * @var array<string, int>
     */
    private const array NAMED = [
        'wild' => 0,
        'good' => 1,
        'great' => 2,
        'ultra' => 3,
        'weather' => 4,
        'best' => 5,
        'shadow' => 6,
        'giovanni' => 6,
        'raid' => 10,
        'egg' => 10,
        'research' => 10,
        'lucky' => 12,
    ];

    private function __construct(
        public int $minimum,
        public ?string $name = null,
    ) {
        if ($minimum < IvSpread::MIN || $minimum > IvSpread::MAX) {
            throw InvalidIv::outOfRange('floor', $minimum);
        }
    }

    /**
     * A wild catch can roll anything: no floor at all.
     */
    public static function wild(): self
    {
        return new self(0, 'wild');
    }

    /**
     * Accepts a source ("best", "lucky", …) or the floor itself ("4").
     */
    public static function fromString(string $input): self
    {
        $input = strtolower(trim($input));

        if (\array_key_exists($input, self::NAMED)) {
            return new self(self::NAMED[$input], $input);
        }

        if (1 === preg_match('/^\d{1,2}$/', $input)) {
            return new self((int) $input);
        }

        throw InvalidIv::unknownFloor($input, array_keys(self::NAMED));
    }

    /**
     * Whether the floor leaves out any spread. A wild catch does not, and has nothing to
     * add to a ranking of all 4096.
     */
    public function restricts(): bool
    {
        return $this->minimum > IvSpread::MIN;
    }

    public function allows(IvSpread $iv): bool
    {
        return min($iv->attack, $iv->defense, $iv->stamina) >= $this->minimum;
    }

    #[\Override]
    public function __toString(): string
    {
        return null === $this->name ? (string) $this->minimum : \sprintf('%s (%d)', $this->name, $this->minimum);
    }
}
