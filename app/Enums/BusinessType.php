<?php

namespace App\Enums;

enum BusinessType: string
{
    case Cafe = 'cafe';
    case Restaurant = 'restaurant';
    case Carwash = 'carwash';
    case Other = 'other';
}
