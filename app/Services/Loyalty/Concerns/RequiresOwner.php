<?php

namespace App\Services\Loyalty\Concerns;

use App\Enums\Role;
use App\Models\Business;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Acțiunile de administrare (afacere, locații, echipă) sunt doar ale ownerului (SDD §3).
 * Policies verifică deja în controllere; serviciile verifică din nou, ca să fie sigure și apelate direct.
 */
trait RequiresOwner
{
    protected function authorizeOwner(User $actor, Business|int $business): void
    {
        if (! $actor->hasRoleIn($business, Role::Owner)) {
            throw new AuthorizationException('Doar ownerul afacerii poate face această acțiune.');
        }
    }
}
