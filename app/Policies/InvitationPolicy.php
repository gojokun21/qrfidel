<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Business;
use App\Models\Invitation;
use App\Models\User;

/**
 * Verifică explicit afacerea invitației, nu doar global scope-ul (apărare în adâncime).
 */
class InvitationPolicy
{
    public function create(User $user, Business $business): bool
    {
        return $user->hasRoleIn($business, Role::Owner);
    }

    public function delete(User $user, Invitation $invitation): bool
    {
        return $user->hasRoleIn($invitation->business_id, Role::Owner);
    }
}
