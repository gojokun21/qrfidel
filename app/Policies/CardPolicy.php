<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Card;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Acțiunile staff-ului pe cardul unui client, plus accesul clientului (guard `customer`) la propriul card.
 */
class CardPolicy
{
    /**
     * Clientul își vede doar cardurile lui. Cardul altcuiva dă 404, ca să nu se afle că există (ADR 010).
     */
    public function own(Customer $customer, Card $card): Response
    {
        return $card->customer_id === $customer->id ? Response::allow() : Response::denyAsNotFound();
    }

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
     * Restricția pe locația managerului, fereastra de 24h și ordinea anulărilor stau în VoidService (ADR 004).
     */
    public function void(User $user, Card $card): bool
    {
        return $user->hasRoleIn($card->business_id, Role::Manager);
    }
}
