<?php

declare(strict_types=1);

namespace App\Domain\Weather\Model;

/**
 * The seven weathers the game knows. Sunny and Clear are the same condition, drawn
 * differently by day and by night; so are the two Partly Cloudy icons.
 */
enum GameWeather: string
{
    case Clear = 'clear';
    case PartlyCloudy = 'partly_cloudy';
    case Overcast = 'overcast';
    case Rainy = 'rainy';
    case Windy = 'windy';
    case Snow = 'snow';
    case Fog = 'fog';

    /**
     * The name the game shows on the weather icon.
     */
    public function label(): string
    {
        return match ($this) {
            self::Clear => 'Sunny/Clear',
            self::PartlyCloudy => 'Partly Cloudy',
            self::Overcast => 'Cloudy',
            self::Rainy => 'Rain',
            self::Windy => 'Windy',
            self::Snow => 'Snow',
            self::Fog => 'Fog',
        };
    }
}
