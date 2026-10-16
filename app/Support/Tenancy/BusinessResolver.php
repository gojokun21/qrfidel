<?php

namespace App\Support\Tenancy;

use App\Models\Business;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Alege afacerea în care lucrează un user (folosit de `EnsureBusinessMember` și de sidebar).
 */
class BusinessResolver
{
    /**
     * Ordinea: afacerea aleasă (din sesiune) dacă e activă, apoi prima afacere activă, apoi prima afacere (suspendată).
     *
     * @param  Collection<int, Business>|null  $businesses  afacerile userului, dacă sunt deja încărcate
     */
    public static function resolve(User $user, mixed $chosenId, ?Collection $businesses = null): ?Business
    {
        $businesses ??= $user->businesses()->orderBy('business_user.id')->get();

        $chosen = is_numeric($chosenId) ? $businesses->firstWhere('id', (int) $chosenId) : null;

        if ($chosen?->isActive()) {
            return $chosen;
        }

        return $businesses->first(fn (Business $business) => $business->isActive()) ?? $businesses->first();
    }
}
