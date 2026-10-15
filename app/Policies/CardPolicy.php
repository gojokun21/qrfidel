<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Card;
use App\Models\User;

/**
 * Acțiunile staff-ului pe cardul unui client. Clientul își vede propriile carduri pe guard-ul `customer` (Sesiunea 6).
 */
class CardPolicy
{
    public function view(User $user, Card $card): bool
    {
        return $user->isMemberOf($card->business_id);
    }

    public function stamp(User $user, Card $card): bool
    {
        return $user->hasRoleIn($card->business_id, Role::Staff);
    }

    public function redeem(User $user, Card $card): bool
    {
        return $user->hasRoleIn($card->business_id, Role::Staff);
    }

    /**
     * Restricția pe locația managerului și fereastra de 24h stau în VoidService (Sesiunea 2).
     */
    public function void(User $user, Card $card): bool
    {
        return $user->hasRoleIn($card->business_id, Role::Manager);
    }
}
