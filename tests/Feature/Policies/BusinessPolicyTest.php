<?php

use App\Enums\Role;
use App\Models\Business;
use App\Models\User;

beforeEach(function () {
    $this->business = Business::factory()->create();
    $this->other = Business::factory()->create();
});

it('lets members and platform admins view a business', function () {
    $staff = User::factory()->withRole($this->business, Role::Staff)->create();
    $admin = User::factory()->platformAdmin()->create();

    expect($staff->can('view', $this->business))->toBeTrue()
        ->and($staff->can('view', $this->other))->toBeFalse()
        ->and($admin->can('view', $this->other))->toBeTrue();
});

it('lets only the owner update the business and manage the team', function (Role $role, bool $allowed) {
    $user = User::factory()->withRole($this->business, $role)->create();

    expect($user->can('update', $this->business))->toBe($allowed)
        ->and($user->can('manageTeam', $this->business))->toBe($allowed);
})->with([
    'owner' => [Role::Owner, true],
    'manager' => [Role::Manager, false],
    'staff' => [Role::Staff, false],
]);

it('does not let an owner update another business', function () {
    $owner = User::factory()->withRole($this->business, Role::Owner)->create();

    expect($owner->can('update', $this->other))->toBeFalse();
});

it('lets only platform admins suspend a business', function () {
    $owner = User::factory()->withRole($this->business, Role::Owner)->create();
    $admin = User::factory()->platformAdmin()->create();

    expect($owner->can('suspend', $this->business))->toBeFalse()
        ->and($admin->can('suspend', $this->business))->toBeTrue();
});

it('does not give platform admins staff powers inside a business', function () {
    $admin = User::factory()->platformAdmin()->create();

    expect($admin->can('update', $this->business))->toBeFalse()
        ->and($admin->can('manageTeam', $this->business))->toBeFalse();
});
