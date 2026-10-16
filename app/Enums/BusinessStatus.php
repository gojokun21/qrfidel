<?php

namespace App\Enums;

enum BusinessStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Activă',
            self::Suspended => 'Suspendată',
        };
    }
}
