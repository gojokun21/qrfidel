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
    $this->owner = User::factory()->withRole($this->business, Role::Owner)->create();
});

it('lists only the locations of the current business', function () {
    Location::factory()->for($this->business)->create(['name' => 'Centru']);
    Location::factory()->create(['name' => 'Locație străină']);

    $this->actingAs($this->owner)
        ->get(route('dashboard.locations.index'))
        ->assertOk()
        ->assertSee('Centru')
        ->assertDontSee('Locație străină');
});

it('lets managers see the locations but not change them', function () {
    $location = Location::factory()->for($this->business)->create(['name' => 'Centru']);
    $manager = User::factory()->withRole($this->business, Role::Manager)->create();

    $this->actingAs($manager)->get(route('dashboard.locations.index'))->assertOk()->assertSee('Centru');
    $this->actingAs($manager)->get(route('dashboard.locations.create'))->assertForbidden();
    $this->actingAs($manager)->post(route('dashboard.locations.store'), ['name' => 'Nouă'])->assertForbidden();
    $this->actingAs($manager)->patch(route('dashboard.locations.update', $location), ['name' => 'X'])->assertForbidden();
    $this->actingAs($manager)->delete(route('dashboard.locations.destroy', $location))->assertForbidden();
});

it('forbids staff from the locations pages', function () {
    $staff = User::factory()->withRole($this->business, Role::Staff)->create();

    $this->actingAs($staff)->get(route('dashboard.locations.index'))->assertForbidden();
});

it('lets the owner create a location with a counter token', function () {
    $this->actingAs($this->owner)
        ->post(route('dashboard.locations.store'), ['name' => 'Gară', 'address' => 'Bd. Gării 10'])
        ->assertRedirect(route('dashboard.locations.index'))
        ->assertSessionHasNoErrors();

    $location = Location::query()->sole();

    expect($location->business_id)->toBe($this->business->id)
        ->and($location->name)->toBe('Gară')
        ->and($location->address)->toBe('Bd. Gării 10')
        ->and($location->counter_token)->toHaveLength(40)
        ->and(AuditLog::query()->where('action', 'location.created')->exists())->toBeTrue();
});

it('validates the location name', function () {
    $this->actingAs($this->owner)
        ->post(route('dashboard.locations.store'), ['name' => ''])
        ->assertSessionHasErrors('name');

    expect(Location::query()->count())->toBe(0);
});

it('ignores a business id sent with the location', function () {
    $foreign = Business::factory()->create();

    $this->actingAs($this->owner)
        ->post(route('dashboard.locations.store'), ['name' => 'Gară', 'business_id' => $foreign->id]);

    expect(Location::query()->withoutGlobalScopes()->sole()->business_id)->toBe($this->business->id);
});

it('lets the owner update a location', function () {
    $location = Location::factory()->for($this->business)->create();

    $this->actingAs($this->owner)->get(route('dashboard.locations.edit', $location))->assertOk();
    $this->actingAs($this->owner)
        ->patch(route('dashboard.locations.update', $location), ['name' => 'Nou', 'address' => null])
        ->assertRedirect(route('dashboard.locations.index'));

    expect($location->refresh()->name)->toBe('Nou')
        ->and($location->address)->toBeNull();
});

it('hides the locations of other businesses', function () {
    $foreign = Location::factory()->create(['name' => 'Străină']);

    $this->actingAs($this->owner)->get(route('dashboard.locations.edit', $foreign))->assertNotFound();
    $this->actingAs($this->owner)->patch(route('dashboard.locations.update', $foreign), ['name' => 'X'])->assertNotFound();
    $this->actingAs($this->owner)->delete(route('dashboard.locations.destroy', $foreign))->assertNotFound();

    expect($foreign->refresh()->name)->toBe('Străină');
});

it('lets the owner delete a location without activity', function () {
    $location = Location::factory()->for($this->business)->create();
    $manager = User::factory()->withRole($this->business, Role::Manager, $location)->create();

    $this->actingAs($this->owner)
        ->delete(route('dashboard.locations.destroy', $location))
        ->assertRedirect(route('dashboard.locations.index'));

    expect(Location::query()->whereKey($location->id)->exists())->toBeFalse()
        ->and($manager->membershipIn($this->business)->location_id)->toBeNull()
        ->and(AuditLog::query()->where('action', 'location.deleted')->exists())->toBeTrue();
});

it('refuses to delete a location with activity', function () {
    $location = Location::factory()->for($this->business)->create();
    $program = Program::factory()->for($this->business)->active()->create();
    CardTransaction::factory()->for(Card::factory()->for($program))->create(['location_id' => $location->id]);

    $this->actingAs($this->owner)
        ->from(route('dashboard.locations.index'))
        ->delete(route('dashboard.locations.destroy', $location))
        ->assertRedirect(route('dashboard.locations.index'))
        ->assertSessionHasErrors(['loyalty' => 'Locația are activitate înregistrată și nu poate fi ștearsă.']);

    expect(Location::query()->whereKey($location->id)->exists())->toBeTrue();
});

it('regenerates the counter token', function () {
    $location = Location::factory()->for($this->business)->create();
    $old = $location->counter_token;

    $this->actingAs($this->owner)
        ->post(route('dashboard.locations.counter-token', $location))
        ->assertRedirect(route('dashboard.locations.index'));

    expect($location->refresh()->counter_token)->not->toBe($old)
        ->and($location->counter_token)->toHaveLength(40)
        ->and(AuditLog::query()->where('action', 'location.counter_token_regenerated')->exists())->toBeTrue();
});
