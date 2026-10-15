<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Membership;
use App\Models\User;

/**
 * Doar ownerul afacerii membrului îi schimbă rolul/locația sau îl scoate din echipă.
 */
class MembershipPolicy
{
    public function update(User $user, Membership $membership): bool
    {
        return $user->hasRoleIn($membership->business_id, Role::Owner);
    }

    public function delete(User $user, Membership $membership): bool
    {
        return $user->hasRoleIn($membership->business_id, Role::Owner);
    }
}
