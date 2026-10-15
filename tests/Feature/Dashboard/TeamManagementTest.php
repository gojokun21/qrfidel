<?php

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Card;
use App\Models\CardTransaction;
use App\Models\Location;
use App\Models\Program;
use App\Models\User;

beforeEach(function () {
    $this->business = Business::factory()->create();
    $this->location = Location::factory()->for($this->business)->create();
    $this->owner = User::factory()->withRole($this->business, Role::Owner)->create(['name' => 'Olga Owner']);
    $this->staff = User::factory()->withRole($this->business, Role::Staff)->create(['name' => 'Sandu Staff']);
});

function membershipOf(User $user, Business $business): int
{
    return $user->membershipIn($business)->id;
}

it('lists only the members of the current business', function () {
    User::factory()->withRole(Business::factory()->create(), Role::Staff)->create(['name' => 'Străin Total']);

    $this->actingAs($this->owner)
        ->get(route('dashboard.team.index'))
        ->assertOk()
        ->assertSee('Olga Owner')
        ->assertSee('Sandu Staff')
        ->assertDontSee('Străin Total');
});

it('forbids managers and staff from managing the team', function (Role $role) {
    $user = User::factory()->withRole($this->business, $role)->create();

    $this->actingAs($user)->get(route('dashboard.team.index'))->assertForbidden();
    $this->actingAs($user)
        ->patch(route('dashboard.team.update', membershipOf($this->staff, $this->business)), ['role' => 'owner'])
        ->assertForbidden();
    $this->actingAs($user)
        ->delete(route('dashboard.team.destroy', membershipOf($this->staff, $this->business)))
        ->assertForbidden();
})->with([Role::Manager, Role::Staff]);

it('changes the role and location of a member', function () {
    $this->actingAs($this->owner)
        ->patch(route('dashboard.team.update', membershipOf($this->staff, $this->business)), [
            'role' => 'manager',
            'location_id' => $this->location->id,
        ])
        ->assertRedirect(route('dashboard.team.index'))
        ->assertSessionHasNoErrors();

    $membership = $this->staff->membershipIn($this->business);

    expect($membership->role)->toBe(Role::Manager)
        ->and($membership->location_id)->toBe($this->location->id)
        ->and(AuditLog::query()->where('action', 'member.role_changed')->exists())->toBeTrue();
});

it('rejects a location of another business', function () {
    $foreign = Location::factory()->create();

    $this->actingAs($this->owner)
        ->patch(route('dashboard.team.update', membershipOf($this->staff, $this->business)), [
            'role' => 'staff',
            'location_id' => $foreign->id,
        ])
        ->assertSessionHasErrors('location_id');

    expect($this->staff->membershipIn($this->business)->location_id)->toBeNull();
});

it('does not bind an owner to a location', function () {
    $this->actingAs($this->owner)
        ->patch(route('dashboard.team.update', membershipOf($this->staff, $this->business)), [
            'role' => 'owner',
            'location_id' => $this->location->id,
        ])
        ->assertSessionHasNoErrors();

    $membership = $this->staff->membershipIn($this->business);

    expect($membership->role)->toBe(Role::Owner)
        ->and($membership->location_id)->toBeNull();
});

it('refuses to demote the last owner', function () {
    $this->actingAs($this->owner)
        ->from(route('dashboard.team.index'))
        ->patch(route('dashboard.team.update', membershipOf($this->owner, $this->business)), ['role' => 'manager'])
        ->assertRedirect(route('dashboard.team.index'))
        ->assertSessionHasErrors(['loyalty' => 'Afacerea trebuie să aibă cel puțin un owner.']);

    expect($this->owner->roleIn($this->business))->toBe(Role::Owner);
});

it('refuses to remove the last owner', function () {
    $this->actingAs($this->owner)
        ->delete(route('dashboard.team.destroy', membershipOf($this->owner, $this->business)))
        ->assertSessionHasErrors(['loyalty' => 'Afacerea trebuie să aibă cel puțin un owner.']);

    expect($this->owner->isMemberOf($this->business))->toBeTrue();
});

it('lets an owner step down when another owner exists', function () {
    $coOwner = User::factory()->withRole($this->business, Role::Owner)->create();

    $this->actingAs($this->owner)
        ->patch(route('dashboard.team.update', membershipOf($this->owner, $this->business)), ['role' => 'staff'])
        ->assertSessionHasNoErrors();

    expect($this->owner->roleIn($this->business))->toBe(Role::Staff)
        ->and($coOwner->roleIn($this->business))->toBe(Role::Owner);
});

it('removes a member but keeps the user and their history', function () {
    $program = Program::factory()->for($this->business)->active()->create();
    $transaction = CardTransaction::factory()->for(Card::factory()->for($program))->create([
        'location_id' => $this->location->id,
        'staff_user_id' => $this->staff->id,
    ]);

    $this->actingAs($this->owner)
        ->delete(route('dashboard.team.destroy', membershipOf($this->staff, $this->business)))
        ->assertRedirect(route('dashboard.team.index'))
        ->assertSessionHasNoErrors();

    expect($this->staff->isMemberOf($this->business))->toBeFalse()
        ->and(User::query()->whereKey($this->staff->id)->exists())->toBeTrue()
        ->and(CardTransaction::query()->withoutGlobalScopes()->find($transaction->id)->staff_user_id)->toBe($this->staff->id)
        ->and(AuditLog::query()->where('action', 'member.removed')->exists())->toBeTrue();
});

it('hides memberships of other businesses', function () {
    $foreign = Business::factory()->create();
    $foreignStaff = User::factory()->withRole($foreign, Role::Staff)->create();
    $id = membershipOf($foreignStaff, $foreign);

    $this->actingAs($this->owner)->patch(route('dashboard.team.update', $id), ['role' => 'owner'])->assertNotFound();
    $this->actingAs($this->owner)->delete(route('dashboard.team.destroy', $id))->assertNotFound();

    expect($foreignStaff->roleIn($foreign))->toBe(Role::Staff);
});

it('validates the role', function () {
    $this->actingAs($this->owner)
        ->patch(route('dashboard.team.update', membershipOf($this->staff, $this->business)), ['role' => 'admin'])
        ->assertSessionHasErrors('role');
});

it('sends an owner who demoted themselves back to the dashboard', function () {
    User::factory()->withRole($this->business, Role::Owner)->create();

    $this->actingAs($this->owner)
        ->patch(route('dashboard.team.update', membershipOf($this->owner, $this->business)), ['role' => 'manager'])
        ->assertRedirect(route('dashboard'));
});
