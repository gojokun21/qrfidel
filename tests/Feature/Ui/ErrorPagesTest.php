<?php

use App\Enums\Role;
use App\Models\Business;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/*
 * Paginile de eroare: în română, cu o cale de ieșire (raport QA 2026-10-18, B2–B4).
 */

it('shows a Romanian 404 with a way back', function () {
    $this->get('/b/nu-exista')
        ->assertNotFound()
        ->assertSee('Pagina nu există')
        ->assertSee('Înapoi la site')
        ->assertDontSee('Not Found');
});

it('lets the team of a suspended business sign out from the 403 page', function () {
    $business = Business::factory()->suspended()->create();
    $manager = User::factory()->withRole($business, Role::Manager)->create();

    $this->actingAs($manager)
        ->get(route('dashboard'))
        ->assertForbidden()
        ->assertSee('Afacerea este suspendată.')
        ->assertSee('action="'.route('logout').'"', false)
        ->assertSee('href="'.route('profile.edit').'"', false)
        ->assertDontSee('Forbidden');
});

it('shows a Romanian 403 to a guest without a sign out form', function () {
    Route::middleware('web')->get('/_test/forbidden', fn () => abort(403));

    $this->get('/_test/forbidden')
        ->assertForbidden()
        ->assertSee('Nu ai acces la această pagină')
        ->assertDontSee('action="'.route('logout').'"', false);
});

it('explains an expired session and offers to sign in again', function () {
    Route::middleware('web')->post('/_test/expired', fn () => abort(419));

    $this->post('/_test/expired')
        ->assertStatus(419)
        ->assertSee('Sesiunea a expirat')
        ->assertSee('href="'.route('login').'"', false)
        ->assertDontSee('Page Expired');
});

it('shows a Romanian 500 without details', function () {
    Route::middleware('web')->get('/_test/broken', fn () => abort(500));

    $this->get('/_test/broken')
        ->assertStatus(500)
        ->assertSee('Ceva n-a mers bine')
        ->assertDontSee('Server Error');
});
