<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Business;
use App\Models\Location;
use App\Models\User;

/**
 * Verifică explicit afacerea locației, nu doar global scope-ul (apărare în adâncime).
 */
class LocationPolicy
{
    public function view(User $user, Location $location): bool
    {
        return $user->isMemberOf($location->business_id);
    }

    public function create(User $user, Business $business): bool
    {
        return $user->hasRoleIn($business, Role::Owner);
    }

    public function update(User $user, Location $location): bool
    {
        return $user->hasRoleIn($location->business_id, Role::Owner);
    }

    public function delete(User $user, Location $location): bool
    {
        return $user->hasRoleIn($location->business_id, Role::Owner);
    }
}
