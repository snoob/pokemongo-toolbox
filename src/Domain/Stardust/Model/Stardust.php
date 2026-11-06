<?php

declare(strict_types=1);

namespace App\Domain\Stardust\Model;

/**
 * An amount of Stardust, and what the same reward pays under a Star Piece.
 */
final readonly class Stardust
{
    private function __construct(
        public int $amount,
        public int $withStarPiece,
    ) {}

    /**
     * A Star Piece adds half to a single reward, rounded down when the half is not whole.
     */
    public static function reward(int $amount): self
    {
        return new self($amount, intdiv($amount * 3, 2));
    }

    public static function none(): self
    {
        return new self(0, 0);
    }

    public function plus(self $other): self
    {
        return new self($this->amount + $other->amount, $this->withStarPiece + $other->withStarPiece);
    }

    /**
     * The same reward earned again and again, each one under the Star Piece on its own.
     */
    public function times(int $count): self
    {
        return new self($this->amount * $count, $this->withStarPiece * $count);
    }

    public function starPieceBonus(): int
    {
        return $this->withStarPiece - $this->amount;
    }
}
