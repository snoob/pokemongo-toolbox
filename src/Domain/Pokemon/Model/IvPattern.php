<?php

declare(strict_types=1);

namespace App\Domain\Pokemon\Model;

use App\Domain\Pokemon\Exception\InvalidIv;

/**
 * IVs as typed, some of them possibly unknown: "15/x/14" leaves the defense to be
 * deduced from the CP. A pattern without unknowns is a plain spread.
 */
final readonly class IvPattern implements \Stringable
{
    /**
     * Only "x": "?" and "*" are shell globs and would need quoting.
     */
    private const string UNKNOWN = '[xX]';

    public function __construct(
        public ?int $attack,
        public ?int $defense,
        public ?int $stamina,
    ) {
        // The spread checks the range and names the faulty stat; an unknown stands in as
        // a 0, which is always legal.
        new IvSpread($attack ?? IvSpread::MIN, $defense ?? IvSpread::MIN, $stamina ?? IvSpread::MIN);
    }

    /**
     * Accepts what `IvSpread::fromString()` accepts, each IV possibly replaced by "x".
     */
    public static function fromString(string $input): self
    {
        $value = '(\d{1,2}|' . self::UNKNOWN . ')';
        $pattern = '#^' . $value . '[/\-\s.]+' . $value . '[/\-\s.]+' . $value . '$#';
        $matches = [];

        if (1 !== preg_match($pattern, trim($input), $matches)) {
            throw InvalidIv::unparsable($input);
        }

        $ivs = array_map(static fn(string $iv): ?int => ctype_digit($iv) ? (int) $iv : null, $matches);

        return new self($ivs[1] ?? null, $ivs[2] ?? null, $ivs[3] ?? null);
    }

    public function isComplete(): bool
    {
        return !\in_array(null, $this->values(), true);
    }

    /**
     * Read back through the spread's own parser: a complete pattern prints as a spread.
     */
    public function toSpread(): ?IvSpread
    {
        return $this->isComplete() ? IvSpread::fromString((string) $this) : null;
    }

    public function matches(IvSpread $iv): bool
    {
        return (
            self::fits($this->attack, $iv->attack)
            && self::fits($this->defense, $iv->defense)
            && self::fits($this->stamina, $iv->stamina)
        );
    }

    #[\Override]
    public function __toString(): string
    {
        return implode('/', array_map(static fn(?int $iv): string => (string) ($iv ?? 'x'), $this->values()));
    }

    /**
     * @return list<?int>
     */
    private function values(): array
    {
        return [$this->attack, $this->defense, $this->stamina];
    }

    private static function fits(?int $known, int $actual): bool
    {
        return null === $known || $known === $actual;
    }
}
