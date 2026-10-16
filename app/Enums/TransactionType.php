<?php

namespace App\Enums;

enum TransactionType: string
{
    case Stamp = 'stamp';
    case Redeem = 'redeem';
    case Void = 'void';
    case Expire = 'expire';
    case Adjust = 'adjust';

    public function label(): string
    {
        return match ($this) {
            self::Stamp => 'Ștampilă',
            self::Redeem => 'Recompensă folosită',
            self::Void => 'Anulare',
            self::Expire => 'Expirare',
            self::Adjust => 'Ajustare',
        };
    }
}
