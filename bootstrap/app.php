<?php

use App\Exceptions\IdempotencyConflict;
use App\Exceptions\InvitationInvalid;
use App\Exceptions\LoyaltyException;
use App\Exceptions\MagicLinkInvalid;
use App\Http\Middleware\EnsureBusinessMember;
use App\Http\Middleware\EnsureHasBusiness;
use App\Http\Middleware\EnsureRole;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'business' => EnsureBusinessMember::class,
            'has-business' => EnsureHasBusiness::class,
            'role' => EnsureRole::class,
        ]);

        // Clienții (rutele `customer.*`) au login-ul și pagina de start proprii, separate de staff (ADR 009).
        $middleware->redirectGuestsTo(fn (Request $request) => $request->routeIs('customer.*') ? route('customer.login') : route('login'));
        $middleware->redirectUsersTo(fn (Request $request) => $request->routeIs('customer.*') ? route('customer.cards') : route('dashboard'));

        // Afacerea curentă trebuie setată înainte de route model binding, ca global scope-ul
        // să ascundă modelele altor afaceri (404, nu 403).
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: EnsureBusinessMember::class);
        $middleware->prependToPriorityList(before: EnsureBusinessMember::class, prepend: EnsureHasBusiness::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Regulile de business refuzate au mesaje în română, gata de arătat utilizatorului.
        // Sunt refuzuri așteptate, nu erori: nu umplu logul.
        $exceptions->dontReport(LoyaltyException::class);

        // Clientul neautentificat merge la login-ul lui fără `url.intended`: login-ul staff-ului folosește intended()
        // și ar ajunge pe /me (ADR 009).
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (in_array('customer', $e->guards(), true) && ! $request->expectsJson()) {
                return redirect()->route('customer.login');
            }
        });

        // Magic link expirat sau alterat (middleware-ul `signed`): pagină cu „cere alt link”, nu un 403 gol.
        $exceptions->render(function (InvalidSignatureException $e, Request $request) {
            if ($request->routeIs('customer.magic.*')) {
                return response()->view('customer.link-invalid', ['message' => (new MagicLinkInvalid)->getMessage()], 403);
            }
        });

        $exceptions->render(function (LoyaltyException $e, Request $request) {
            if ($e instanceof InvitationInvalid) {
                return response()->view('invitations.invalid', ['message' => $e->getMessage()], $e->status);
            }

            if ($e instanceof MagicLinkInvalid) {
                return response()->view('customer.link-invalid', ['message' => $e->getMessage()], 403);
            }

            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], $e instanceof IdempotencyConflict ? 409 : 422);
            }

            return back()
                ->withInput($request->except(['password', 'password_confirmation', 'current_password']))
                ->withErrors(['loyalty' => $e->getMessage()]);
        });
    })->create();
