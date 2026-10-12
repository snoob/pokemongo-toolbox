<?php

declare(strict_types=1);

namespace App\Domain\Pokemon\Model;

/**
 * The eighteen types. Weather boosts are granted by type, so a species knows its own.
 */
enum PokemonType: string
{
    case Normal = 'normal';
    case Fighting = 'fighting';
    case Flying = 'flying';
    case Poison = 'poison';
    case Ground = 'ground';
    case Rock = 'rock';
    case Bug = 'bug';
    case Ghost = 'ghost';
    case Steel = 'steel';
    case Fire = 'fire';
    case Water = 'water';
    case Grass = 'grass';
    case Electric = 'electric';
    case Psychic = 'psychic';
    case Ice = 'ice';
    case Dragon = 'dragon';
    case Dark = 'dark';
    case Fairy = 'fairy';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
