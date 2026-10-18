<?php

use App\Models\Business;
use App\Models\Customer;
use App\Models\Program;
use App\Models\User;
use Illuminate\Auth\SessionGuard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;

/*
 * Un cookie „remember” fără sesiune (sesiune expirată sau golită la logout-ul celuilalt guard) relogează userul.
 * Relogarea regenerează tokenul CSRF; dacă s-ar întâmpla în mijlocul randării, formularul de pe pagină ar purta
 * tokenul vechi și următorul POST ar primi 419 (raport QA 2026-10-18).
 */

/**
 * Cookie-ul „remember” al guard-ului, exact cum îl emite un login real; sesiunea rămâne goală.
 */
function rememberCookie(string $guard, Customer|User $user): array
{
    /** @var SessionGuard $sessionGuard */
    $sessionGuard = Auth::guard($guard);
    $sessionGuard->login($user, remember: true);
    $name = $sessionGuard->getRecallerName();
    $value = Cookie::queued($name)->getValue();

    Auth::forgetGuards();
    session()->flush();

    return [$name => $value];
}

it('renders the staff login form with the token the session keeps when a customer is remembered', function () {
    $response = $this->withCookies(rememberCookie('customer', Customer::factory()->create()))->get(route('login'));

    $response->assertOk()->assertSee('name="_token" value="'.session()->token().'"', false);
    $this->assertAuthenticated('customer');
});

it('renders the enrollment page with the token the session keeps for a remembered customer', function () {
    $business = Business::factory()->create();
    Program::factory()->for($business)->active()->create();

    $response = $this->withCookies(rememberCookie('customer', Customer::factory()->create()))->get(route('customer.business', $business));

    $response->assertOk()->assertSee('name="_token" value="'.session()->token().'"', false);
});

it('renders the dashboard with the token the session keeps for a remembered staff member', function () {
    $user = User::factory()->create();

    $response = $this->withCookies(rememberCookie('web', $user))->get(route('profile.edit'));

    $response->assertOk()->assertSee('name="_token" value="'.session()->token().'"', false);
    $this->assertAuthenticatedAs($user);
});
