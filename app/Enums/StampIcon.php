<?php

namespace App\Enums;

/**
 * Iconul ștampilei de pe card. Lista e păstrată și în CHECK-ul din DB (`programs_stamp_icon_check`)
 * și în componenta `x-icon`.
 */
enum StampIcon: string
{
    case Coffee = 'coffee';
    case Utensils = 'utensils';
    case Cake = 'cake';
    case Car = 'car';
    case Scissors = 'scissors';
    case Star = 'star';
    case Heart = 'heart';
    case Gift = 'gift';

    public function label(): string
    {
        return match ($this) {
            self::Coffee => 'Cafea',
            self::Utensils => 'Tacâmuri',
            self::Cake => 'Desert',
            self::Car => 'Mașină',
            self::Scissors => 'Foarfecă',
            self::Star => 'Stea',
            self::Heart => 'Inimă',
            self::Gift => 'Cadou',
        };
    }
}
