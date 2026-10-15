<?php

namespace App\Http\Middleware;

use App\Models\Business;
use App\Models\User;
use App\Support\Tenancy\CurrentBusiness;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stabilește afacerea curentă pentru staff/owner (SDD §3: EnsureBusinessMember + SetBusinessContext, vezi ADR 003).
 * Folosește afacerea aleasă în sesiune dacă userul e membru, altfel primul lui membership.
 */
class EnsureBusinessMember
{
    public const SESSION_KEY = 'current_business_id';

    public function __construct(private CurrentBusiness $currentBusiness) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        $business = $this->resolveBusiness($request, $user);

        abort_if($business === null, 403, 'Contul tău nu face parte din nicio afacere.');
        abort_unless($business->isActive(), 403, 'Afacerea este suspendată.');

        $request->session()->put(self::SESSION_KEY, $business->getKey());

        $previous = $this->currentBusiness->get();
        $this->currentBusiness->set($business);

        try {
            return $next($request);
        } finally {
            $this->currentBusiness->set($previous);
        }
    }

    /**
     * Ordinea: afacerea din sesiune dacă e activă, apoi prima afacere activă, apoi prima afacere (suspendată → 403).
     */
    private function resolveBusiness(Request $request, User $user): ?Business
    {
        $businesses = $user->businesses()->orderBy('business_user.id')->get();
        $chosenId = $request->session()->get(self::SESSION_KEY);

        $chosen = is_numeric($chosenId) ? $businesses->firstWhere('id', (int) $chosenId) : null;

        if ($chosen?->isActive()) {
            return $chosen;
        }

        return $businesses->first(fn (Business $business) => $business->isActive()) ?? $businesses->first();
    }
}
