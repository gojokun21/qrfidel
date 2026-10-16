<?php

namespace App\Http\Middleware;

use App\Enums\BusinessStatus;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Un user înregistrat care nu face încă parte din nicio afacere ajunge la formularul de creare (self-serve),
 * iar adminul platformei la panoul `/admin`.
 * Se pune înaintea middleware-ului `business`, care altfel ar răspunde 403.
 */
class EnsureHasBusiness
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        // Adminul platformei fără nicio afacere activă (de ex. și-a suspendat afacerea de test) are panoul lui (ADR 014).
        if ($user->isPlatformAdmin() && ! $user->businesses()->where('status', BusinessStatus::Active)->exists()) {
            return redirect()->route('admin.index');
        }

        if (! $user->businesses()->exists()) {
            return redirect()->route('dashboard.business.create');
        }

        return $next($request);
    }
}
