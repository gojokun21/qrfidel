<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use App\Models\User;
use App\Support\Tenancy\CurrentBusiness;
use Closure;
use Illuminate\Http\Request;
use InvalidArgumentException;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cere un rol minim în afacerea curentă: `role:manager` lasă managerii și ownerii.
 * Se folosește după middleware-ul `business`.
 */
class EnsureRole
{
    public function __construct(private CurrentBusiness $currentBusiness) {}

    public function handle(Request $request, Closure $next, string $role): Response
    {
        $business = $this->currentBusiness->get();

        if ($business === null) {
            throw new LogicException('The role middleware requires the business middleware to run first.');
        }

        $minimum = Role::tryFrom($role) ?? throw new InvalidArgumentException("Unknown role [{$role}] in the role middleware.");
        $user = $request->user();

        abort_unless(
            $user instanceof User && $user->hasRoleIn($business, $minimum),
            403,
            'Nu ai permisiunea necesară pentru această acțiune.',
        );

        return $next($request);
    }
}
