<?php

use App\Enums\Role;
use App\Models\Business;
use App\Models\User;
use App\Support\Tenancy\CurrentBusiness;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $current = fn () => response()->json(['business' => app(CurrentBusiness::class)->id()]);

    Route::middleware(['web', 'auth', 'business'])->get('/_test/tenant', $current);
    Route::middleware(['web', 'auth', 'business', 'role:manager'])->get('/_test/manager', $current);
    Route::middleware(['web', 'auth', 'business', 'role:owner'])->get('/_test/owner', $current);
});

it('redirects guests to login', function () {
    $this->get('/_test/tenant')->assertRedirect(route('login'));
});

it('forbids users without any business', function () {
    $this->actingAs(User::factory()->create())
        ->get('/_test/tenant')
        ->assertForbidden();
});

it('sets the only business of the user as current', function () {
    $business = Business::factory()->create();
    $user = User::factory()->withRole($business, Role::Staff)->create();

    $this->actingAs($user)
        ->get('/_test/tenant')
        ->assertOk()
        ->assertJson(['business' => $business->id])
        ->assertSessionHas('current_business_id', $business->id);
});

it('uses the business chosen in the session when the user is a member', function () {
    [$first, $second] = Business::factory()->count(2)->create();
    $user = User::factory()->withRole($first, Role::Staff)->withRole($second, Role::Owner)->create();

    $this->actingAs($user)
        ->withSession(['current_business_id' => $second->id])
        ->get('/_test/tenant')
        ->assertJson(['business' => $second->id]);
});

it('ignores a session business the user is not a member of', function () {
    $mine = Business::factory()->create();
    $foreign = Business::factory()->create();
    $user = User::factory()->withRole($mine, Role::Owner)->create();

    $this->actingAs($user)
        ->withSession(['current_business_id' => $foreign->id])
        ->get('/_test/tenant')
        ->assertJson(['business' => $mine->id])
        ->assertSessionHas('current_business_id', $mine->id);
});

it('forbids access to a suspended business', function () {
    $business = Business::factory()->suspended()->create();
    $user = User::factory()->withRole($business, Role::Owner)->create();

    $this->actingAs($user)->get('/_test/tenant')->assertForbidden();
});

it('falls back to an active business when the preferred one is suspended', function () {
    $suspended = Business::factory()->suspended()->create();
    $active = Business::factory()->create();
    $user = User::factory()->withRole($suspended, Role::Owner)->withRole($active, Role::Staff)->create();

    $this->actingAs($user)->get('/_test/tenant')->assertJson(['business' => $active->id]);
    $this->actingAs($user)
        ->withSession(['current_business_id' => $suspended->id])
        ->get('/_test/tenant')
        ->assertJson(['business' => $active->id]);
});

it('does not leak the business context after the request', function () {
    $business = Business::factory()->create();
    $user = User::factory()->withRole($business, Role::Staff)->create();

    $this->actingAs($user)->get('/_test/tenant')->assertOk();

    expect(app(CurrentBusiness::class)->id())->toBeNull();
});

it('enforces the minimum role', function (Role $role, int $managerStatus, int $ownerStatus) {
    $business = Business::factory()->create();
    $user = User::factory()->withRole($business, $role)->create();

    $this->actingAs($user)->get('/_test/manager')->assertStatus($managerStatus);
    $this->actingAs($user)->get('/_test/owner')->assertStatus($ownerStatus);
})->with([
    'staff' => [Role::Staff, 403, 403],
    'manager' => [Role::Manager, 200, 403],
    'owner' => [Role::Owner, 200, 200],
]);

it('fails loudly on an unknown role name', function () {
    Route::middleware(['web', 'auth', 'business', 'role:admin'])->get('/_test/typo', fn () => 'ok');
    $business = Business::factory()->create();

    $this->withoutExceptionHandling()
        ->actingAs(User::factory()->withRole($business, Role::Owner)->create())
        ->get('/_test/typo');
})->throws(InvalidArgumentException::class, 'Unknown role [admin]');

it('checks the role in the current business, not in another one', function () {
    [$owned, $staffed] = Business::factory()->count(2)->create();
    $user = User::factory()->withRole($owned, Role::Owner)->withRole($staffed, Role::Staff)->create();

    $this->actingAs($user)
        ->withSession(['current_business_id' => $staffed->id])
        ->get('/_test/manager')
        ->assertForbidden();
});
