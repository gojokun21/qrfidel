<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Un user înregistrat care nu face încă parte din nicio afacere ajunge la formularul de creare (self-serve).
 * Se pune înaintea middleware-ului `business`, care altfel ar răspunde 403.
 */
class EnsureHasBusiness
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ! $user->businesses()->exists()) {
            return redirect()->route('dashboard.business.create');
        }

        return $next($request);
    }
}
