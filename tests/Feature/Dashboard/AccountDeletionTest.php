<?php

use App\Enums\Role;
use App\Models\Business;
use App\Models\Card;
use App\Models\CardTransaction;
use App\Models\Location;
use App\Models\Program;
use App\Models\User;

it('refuses to delete the account of the only owner of a business', function () {
    $business = Business::factory()->create(['name' => 'Cafenea Unică']);
    $owner = User::factory()->withRole($business, Role::Owner)->create();

    $this->actingAs($owner)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHasErrorsIn('userDeletion', 'password');

    expect(User::query()->whereKey($owner->id)->exists())->toBeTrue();
    $this->assertAuthenticatedAs($owner);
});

it('refuses to delete an account with activity in the journal', function () {
    $business = Business::factory()->create();
    $location = Location::factory()->for($business)->create();
    $staff = User::factory()->withRole($business, Role::Staff)->create();
    $program = Program::factory()->for($business)->active()->create();
    CardTransaction::factory()->for(Card::factory()->for($program))->create([
        'location_id' => $location->id,
        'staff_user_id' => $staff->id,
    ]);

    $this->actingAs($staff)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertSessionHasErrorsIn('userDeletion', 'password');

    expect(User::query()->whereKey($staff->id)->exists())->toBeTrue();
});

it('deletes an account without activity and removes its memberships', function () {
    $business = Business::factory()->create();
    User::factory()->withRole($business, Role::Owner)->create();
    $staff = User::factory()->withRole($business, Role::Staff)->create();

    $this->actingAs($staff)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertRedirect('/');

    expect(User::query()->whereKey($staff->id)->exists())->toBeFalse()
        ->and($business->members()->count())->toBe(1);
});

it('lets a co-owner delete their account', function () {
    $business = Business::factory()->create();
    User::factory()->withRole($business, Role::Owner)->create();
    $coOwner = User::factory()->withRole($business, Role::Owner)->create();

    $this->actingAs($coOwner)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertRedirect('/');

    expect(User::query()->whereKey($coOwner->id)->exists())->toBeFalse();
});
