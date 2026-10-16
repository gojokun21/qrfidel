<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\StampRequest;
use App\Models\User;

/**
 * Cererile de ștampilă de la tejghea (SDD §5.3): orice angajat al afacerii le poate trata. Restricția pe locația
 * membrului și starea cererii se verifică în StampRequestService.
 */
class StampRequestPolicy
{
    public function handle(User $user, StampRequest $stampRequest): bool
    {
        return $user->hasRoleIn($stampRequest->business_id, Role::Staff);
    }
}
