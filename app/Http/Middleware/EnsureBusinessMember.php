<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Tenancy\BusinessResolver;
use App\Support\Tenancy\CurrentBusiness;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stabilește afacerea curentă pentru staff/owner (SDD §3: EnsureBusinessMember + SetBusinessContext, vezi ADR 003).
 * Afacerea se alege cu BusinessResolver: cea din sesiune dacă e activă, apoi prima activă, apoi prima (suspendată → 403).
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

        $business = BusinessResolver::resolve($user, $request->session()->get(self::SESSION_KEY));

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
}
