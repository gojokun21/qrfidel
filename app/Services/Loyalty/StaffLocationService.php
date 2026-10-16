<?php

namespace App\Services\Loyalty;

use App\Models\Business;
use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Locația în care scanează angajatul (ADR 011). Un membru legat de o locație lucrează doar acolo; ceilalți
 * aleg locația (păstrată în sesiune de controller), iar o afacere cu o singură locație o primește automat.
 */
class StaffLocationService
{
    /**
     * Locațiile în care poate scana: doar cea fixă, dacă membrul e legat de una.
     *
     * @return Collection<int, Location>
     */
    public function selectable(User $user, Business $business): Collection
    {
        $fixed = $user->membershipIn($business)?->location_id;

        return Location::query()->withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->when($fixed !== null, fn ($query) => $query->whereKey($fixed))
            ->orderBy('name')
            ->get();
    }

    /**
     * Locația de lucru: cea fixă, apoi cea aleasă (dacă e încă permisă), apoi singura disponibilă; altfel null.
     */
    public function current(User $user, Business $business, ?int $chosenId): ?Location
    {
        $locations = $this->selectable($user, $business);

        if ($locations->count() === 1) {
            return $locations->first();
        }

        return $chosenId === null ? null : $locations->firstWhere('id', $chosenId);
    }
}
