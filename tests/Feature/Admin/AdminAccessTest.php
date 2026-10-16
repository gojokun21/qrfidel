<?php

use App\Enums\Role;
use App\Http\Middleware\EnsureBusinessMember;
use App\Models\Business;
use App\Models\User;

beforeEach(function () {
    $this->business = Business::factory()->create();
    $this->admin = User::factory()->platformAdmin()->create();
    $this->owner = User::factory()->withRole($this->business, Role::Owner)->create();

    $this->adminRoutes = fn () => [
        ['get', route('admin.index')],
        ['get', route('admin.businesses.show', $this->business)],
        ['post', route('admin.businesses.suspend', $this->business)],
        ['post', route('admin.businesses.reactivate', $this->business)],
    ];
});

it('sends guests to the login page', function () {
    foreach (($this->adminRoutes)() as [$method, $url]) {
        $this->{$method}($url)->assertRedirect(route('login'));
    }
});

it('forbids users who are not platform admins, owners included', function () {
    $nobody = User::factory()->create();

    foreach ([$this->owner, $nobody] as $user) {
        foreach (($this->adminRoutes)() as [$method, $url]) {
            $this->actingAs($user)->{$method}($url)->assertForbidden();
        }
    }

    expect($this->business->fresh()->isActive())->toBeTrue();
});

it('opens the admin panel for platform admins', function () {
    $this->actingAs($this->admin)->get(route('admin.index'))->assertOk()->assertSee('Admin platformă');
});

it('sends an admin without a business from the dashboard to the admin panel', function () {
    $this->actingAs($this->admin)->get(route('dashboard'))->assertRedirect(route('admin.index'));
    $this->actingAs($this->admin)->get(route('staff.scan'))->assertRedirect(route('admin.index'));
});

it('sends an admin whose businesses are all suspended to the admin panel', function () {
    $admin = User::factory()->platformAdmin()->withRole(Business::factory()->suspended()->create(), Role::Owner)->create();

    $this->actingAs($admin)->get(route('dashboard'))->assertRedirect(route('admin.index'));
});

it('keeps the dashboard for an admin who also belongs to a business', function () {
    $admin = User::factory()->platformAdmin()->withRole($this->business, Role::Owner)->create();

    $this->actingAs($admin)->get(route('dashboard'))->assertOk();
});

it('gives the admin no staff powers in a business', function () {
    $this->actingAs($this->admin)
        ->withSession([EnsureBusinessMember::SESSION_KEY => $this->business->id])
        ->get(route('dashboard.reports'))
        ->assertForbidden();

    $this->actingAs($this->admin)
        ->withSession([EnsureBusinessMember::SESSION_KEY => $this->business->id])
        ->get(route('dashboard.transactions.index'))
        ->assertForbidden();
});

it('shows the admin link in the sidebar only to platform admins', function () {
    $this->actingAs($this->admin)->get(route('admin.index'))->assertSee('href="'.route('admin.index').'"', false);
    $this->actingAs($this->owner)->get(route('dashboard'))->assertDontSee('href="'.route('admin.index').'"', false);
});
