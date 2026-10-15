<?php

use App\Enums\Role;
use App\Models\Business;
use App\Models\Invitation;
use App\Models\User;
use App\Services\Loyalty\InvitationService;
use App\Services\Loyalty\LocationService;
use App\Services\Loyalty\TeamService;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
    $this->business = Business::factory()->create();
    $this->other = Business::factory()->create();
    $this->staff = User::factory()->withRole($this->business, Role::Staff)->create();
});

it('lets only owners of the same business manage memberships', function (Role $role, bool $allowed) {
    $user = User::factory()->withRole($this->business, $role)->create();
    $membership = $this->staff->membershipIn($this->business);

    expect($user->can('update', $membership))->toBe($allowed)
        ->and($user->can('delete', $membership))->toBe($allowed);
})->with([
    'owner' => [Role::Owner, true],
    'manager' => [Role::Manager, false],
    'staff' => [Role::Staff, false],
]);

it('does not let an owner manage memberships of another business', function () {
    $owner = User::factory()->withRole($this->other, Role::Owner)->create();

    expect($owner->can('update', $this->staff->membershipIn($this->business)))->toBeFalse();
});

it('lets only owners of the same business invite and revoke', function () {
    $owner = User::factory()->withRole($this->business, Role::Owner)->create();
    $foreignOwner = User::factory()->withRole($this->other, Role::Owner)->create();
    $invitation = Invitation::factory()->for($this->business)->create();

    expect($owner->can('create', [Invitation::class, $this->business]))->toBeTrue()
        ->and($owner->can('delete', $invitation))->toBeTrue()
        ->and($this->staff->can('create', [Invitation::class, $this->business]))->toBeFalse()
        ->and($foreignOwner->can('create', [Invitation::class, $this->business]))->toBeFalse()
        ->and($foreignOwner->can('delete', $invitation))->toBeFalse();
});

it('rejects team changes from a non-owner at the service level', function () {
    $manager = User::factory()->withRole($this->business, Role::Manager)->create();

    app(TeamService::class)->changeRole($manager, $this->staff->membershipIn($this->business), Role::Owner, null);
})->throws(AuthorizationException::class);

it('rejects invitations from a non-owner at the service level', function () {
    app(InvitationService::class)->invite($this->staff, $this->business, 'x@exemplu.test', Role::Staff, null);
})->throws(AuthorizationException::class);

it('rejects location changes from a non-owner at the service level', function () {
    app(LocationService::class)->create($this->staff, $this->business, ['name' => 'X']);
})->throws(AuthorizationException::class);
