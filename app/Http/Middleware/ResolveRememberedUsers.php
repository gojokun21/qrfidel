<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Relogarea din cookie-ul „remember” (`web` sau `customer`) regenerează sesiunea și tokenul CSRF.
 * Dacă s-ar întâmpla la prima verificare din view (după ce un formular și-a scris `@csrf`), formularul ar trimite
 * un token vechi și ar primi 419 – de exemplu după logout-ul staff-ului într-un browser cu clientul ținut minte.
 * Aici gărzile cu cookie „remember” se rezolvă înainte de controller și de randare (raport QA 2026-10-18).
 */
class ResolveRememberedUsers
{
    private const GUARDS = ['web', 'customer'];

    public function handle(Request $request, Closure $next): Response
    {
        foreach (self::GUARDS as $name) {
            $guard = Auth::guard($name);

            if ($guard instanceof SessionGuard && $request->cookies->has($guard->getRecallerName())) {
                $guard->check();
            }
        }

        return $next($request);
    }
}
