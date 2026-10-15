<?php

namespace App\Enums;

enum TransactionType: string
{
    case Stamp = 'stamp';
    case Redeem = 'redeem';
    case Void = 'void';
    case Expire = 'expire';
    case Adjust = 'adjust';
}
