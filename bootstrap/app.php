<?php

use App\Exceptions\IdempotencyConflict;
use App\Exceptions\InvitationInvalid;
use App\Exceptions\LoyaltyException;
use App\Http\Middleware\EnsureBusinessMember;
use App\Http\Middleware\EnsureHasBusiness;
use App\Http\Middleware\EnsureRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
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

        // Afacerea curentă trebuie setată înainte de route model binding, ca global scope-ul
        // să ascundă modelele altor afaceri (404, nu 403).
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: EnsureBusinessMember::class);
        $middleware->prependToPriorityList(before: EnsureBusinessMember::class, prepend: EnsureHasBusiness::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Regulile de business refuzate au mesaje în română, gata de arătat utilizatorului.
        // Sunt refuzuri așteptate, nu erori: nu umplu logul.
        $exceptions->dontReport(LoyaltyException::class);
        $exceptions->render(function (LoyaltyException $e, Request $request) {
            if ($e instanceof InvitationInvalid) {
                return response()->view('invitations.invalid', ['message' => $e->getMessage()], $e->status);
            }

            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], $e instanceof IdempotencyConflict ? 409 : 422);
            }

            return back()
                ->withInput($request->except(['password', 'password_confirmation', 'current_password']))
                ->withErrors(['loyalty' => $e->getMessage()]);
        });
    })->create();
