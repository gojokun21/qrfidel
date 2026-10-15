<?php

namespace App\Enums;

enum BusinessType: string
{
    case Cafe = 'cafe';
    case Restaurant = 'restaurant';
    case Carwash = 'carwash';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cafe => 'Cafenea',
            self::Restaurant => 'Restaurant',
            self::Carwash => 'Spălătorie auto',
            self::Other => 'Altceva',
        };
    }
}
