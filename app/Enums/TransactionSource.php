<?php

namespace App\Enums;

enum TransactionSource: string
{
    case Scan = 'scan';
    case Request = 'request';
    case Manual = 'manual';
}
