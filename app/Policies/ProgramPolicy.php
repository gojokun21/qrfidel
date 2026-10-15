<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Business;
use App\Models\Program;
use App\Models\User;

/**
 * Verifică explicit afacerea programului, nu doar global scope-ul (apărare în adâncime).
 */
class ProgramPolicy
{
    public function view(User $user, Program $program): bool
    {
        return $user->isMemberOf($program->business_id);
    }

    public function create(User $user, Business $business): bool
    {
        return $user->hasRoleIn($business, Role::Owner);
    }

    public function update(User $user, Program $program): bool
    {
        return $user->hasRoleIn($program->business_id, Role::Owner);
    }

    public function delete(User $user, Program $program): bool
    {
        return $user->hasRoleIn($program->business_id, Role::Owner);
    }
}
