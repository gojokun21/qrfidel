<?php

namespace App\Enums;

enum RewardStatus: string
{
    case Available = 'available';
    case Redeemed = 'redeemed';
    case Expired = 'expired';
}
