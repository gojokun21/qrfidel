<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Panoul `/admin` (ADR 014): doar adminul platformei. Rutele rulează fără context de afacere,
 * deci global scope-ul BelongsToBusiness nu filtrează; serviciile filtrează explicit.
 */
class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->isPlatformAdmin(), 403, 'Doar adminul platformei are acces aici.');

        return $next($request);
    }
}
