<?php

declare(strict_types=1);

namespace App\Domain\Pokemon\Model;

use App\Domain\Pokemon\Exception\InvalidIv;

final readonly class IvSpread implements \Stringable
{
    public const int MIN = 0;
    public const int MAX = 15;

    /** Number of distinct spreads: 16³. */
    public const int COMBINATIONS = 4096;

    public function __construct(
        public int $attack,
        public int $defense,
        public int $stamina,
    ) {
        foreach (['attack' => $attack, 'defense' => $defense, 'stamina' => $stamina] as $name => $value) {
            if ($value < self::MIN || $value > self::MAX) {
                throw InvalidIv::outOfRange($name, $value);
            }
        }
    }

    /**
     * Accepts "1/15/14", "1-15-14" or "1 15 14".
     */
    public static function fromString(string $input): self
    {
        $matches = [];

        if (1 !== preg_match('#^(\d{1,2})[/\-\s.]+(\d{1,2})[/\-\s.]+(\d{1,2})$#', trim($input), $matches)) {
            throw InvalidIv::unparsable($input);
        }

        // The three groups exist whenever the pattern matched; the fallbacks say so
        // to the analyser rather than trusting an index blindly.
        return new self(
            (int) ($matches[1] ?? throw InvalidIv::unparsable($input)),
            (int) ($matches[2] ?? throw InvalidIv::unparsable($input)),
            (int) ($matches[3] ?? throw InvalidIv::unparsable($input)),
        );
    }

    /**
     * Every spread a Pokémon can have, in a stable order.
     *
     * @return \Generator<int, self>
     */
    public static function all(): \Generator
    {
        for ($attack = self::MIN; $attack <= self::MAX; ++$attack) {
            for ($defense = self::MIN; $defense <= self::MAX; ++$defense) {
                for ($stamina = self::MIN; $stamina <= self::MAX; ++$stamina) {
                    yield new self($attack, $defense, $stamina);
                }
            }
        }
    }

    public function equals(self $other): bool
    {
        return (
            $this->attack === $other->attack
            && $this->defense === $other->defense
            && $this->stamina === $other->stamina
        );
    }

    #[\Override]
    public function __toString(): string
    {
        return \sprintf('%d/%d/%d', $this->attack, $this->defense, $this->stamina);
    }
}
