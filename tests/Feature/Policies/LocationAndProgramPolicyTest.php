<?php

use App\Enums\Role;
use App\Models\Business;
use App\Models\Location;
use App\Models\Program;
use App\Models\User;

beforeEach(function () {
    $this->business = Business::factory()->create();
    $this->other = Business::factory()->create();
});

dataset('business resources', [
    'location' => [Location::class],
    'program' => [Program::class],
]);

it('lets any member view the resource of their business only', function (string $class) {
    $mine = $class::factory()->for($this->business)->create();
    $foreign = $class::factory()->for($this->other)->create();
    $staff = User::factory()->withRole($this->business, Role::Staff)->create();

    expect($staff->can('view', $mine))->toBeTrue()
        ->and($staff->can('view', $foreign))->toBeFalse();
})->with('business resources');

it('lets only the owner create, update and delete', function (string $class, Role $role, bool $allowed) {
    $resource = $class::factory()->for($this->business)->create();
    $user = User::factory()->withRole($this->business, $role)->create();

    expect($user->can('create', [$class, $this->business]))->toBe($allowed)
        ->and($user->can('update', $resource))->toBe($allowed)
        ->and($user->can('delete', $resource))->toBe($allowed);
})->with('business resources')->with([
    'owner' => [Role::Owner, true],
    'manager' => [Role::Manager, false],
    'staff' => [Role::Staff, false],
]);

it('does not let the owner of X touch the resources of Y', function (string $class) {
    $foreign = $class::factory()->for($this->other)->create();
    $owner = User::factory()->withRole($this->business, Role::Owner)->create();

    expect($owner->can('create', [$class, $this->other]))->toBeFalse()
        ->and($owner->can('update', $foreign))->toBeFalse()
        ->and($owner->can('delete', $foreign))->toBeFalse();
})->with('business resources');

it('lets managers and owners list the locations of their business only', function (Role $role, bool $allowed) {
    $business = Business::factory()->create();
    $user = User::factory()->withRole($business, $role)->create();

    expect($user->can('viewAny', [Location::class, $business]))->toBe($allowed)
        ->and($user->can('viewAny', [Location::class, Business::factory()->create()]))->toBeFalse();
})->with([
    'owner' => [Role::Owner, true],
    'manager' => [Role::Manager, true],
    'staff' => [Role::Staff, false],
]);
