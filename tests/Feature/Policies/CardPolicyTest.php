<?php

use App\Enums\Role;
use App\Models\Business;
use App\Models\Card;
use App\Models\Program;
use App\Models\User;

beforeEach(function () {
    $this->business = Business::factory()->create();
    $this->card = Card::factory()->for(Program::factory()->for($this->business)->active())->create();

    $other = Business::factory()->create();
    $this->foreignCard = Card::factory()->for(Program::factory()->for($other)->active())->create();
});

it('lets every member view, stamp and redeem cards of their business', function (Role $role) {
    $user = User::factory()->withRole($this->business, $role)->create();

    expect($user->can('view', $this->card))->toBeTrue()
        ->and($user->can('stamp', $this->card))->toBeTrue()
        ->and($user->can('redeem', $this->card))->toBeTrue();
})->with([Role::Owner, Role::Manager, Role::Staff]);

it('does not let staff of business X view or stamp a card of business Y', function (Role $role) {
    $user = User::factory()->withRole($this->business, $role)->create();

    expect($user->can('view', $this->foreignCard))->toBeFalse()
        ->and($user->can('stamp', $this->foreignCard))->toBeFalse()
        ->and($user->can('redeem', $this->foreignCard))->toBeFalse()
        ->and($user->can('void', $this->foreignCard))->toBeFalse();
})->with([Role::Owner, Role::Manager, Role::Staff]);

it('lets only managers and owners void', function (Role $role, bool $allowed) {
    $user = User::factory()->withRole($this->business, $role)->create();

    expect($user->can('void', $this->card))->toBe($allowed);
})->with([
    'owner' => [Role::Owner, true],
    'manager' => [Role::Manager, true],
    'staff' => [Role::Staff, false],
]);

it('gives users without membership and platform admins no card access', function () {
    $stranger = User::factory()->create();
    $admin = User::factory()->platformAdmin()->create();

    foreach (['view', 'stamp', 'redeem', 'void'] as $ability) {
        expect($stranger->can($ability, $this->card))->toBeFalse()
            ->and($admin->can($ability, $this->card))->toBeFalse();
    }
});
