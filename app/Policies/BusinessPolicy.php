<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Business;
use App\Models\User;

class BusinessPolicy
{
    /**
     * Lista tuturor afacerilor (panoul de admin, ADR 014).
     */
    public function viewAny(User $user): bool
    {
        return $user->isPlatformAdmin();
    }

    public function view(User $user, Business $business): bool
    {
        return $user->isPlatformAdmin() || $user->isMemberOf($business);
    }

    public function update(User $user, Business $business): bool
    {
        return $user->hasRoleIn($business, Role::Owner);
    }

    public function manageTeam(User $user, Business $business): bool
    {
        return $user->hasRoleIn($business, Role::Owner);
    }

    public function suspend(User $user, Business $business): bool
    {
        return $user->isPlatformAdmin();
    }

    public function reactivate(User $user, Business $business): bool
    {
        return $user->isPlatformAdmin();
    }
}
