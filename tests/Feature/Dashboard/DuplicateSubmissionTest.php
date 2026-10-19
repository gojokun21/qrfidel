<?php

use App\Enums\BusinessType;
use App\Enums\Role;
use App\Models\Business;
use App\Models\Location;
use App\Models\Program;
use App\Models\User;
use App\Services\Loyalty\IdempotentCreation;
use App\Services\Loyalty\ProgramTemplates;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/*
 * Dublu-click sau retrimiterea formularului: aceeași cheie de idempotency nu creează a doua oară (raport QA 2026-10-18, B1/B7).
 */

describe('business', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->payload = ['name' => 'Cafeneaua Verde', 'type' => 'cafe', 'timezone' => 'Europe/Bucharest'];
    });

    it('creates the business once when the same form is submitted twice', function () {
        $payload = [...$this->payload, 'idempotency_key' => (string) Str::uuid()];

        $this->actingAs($this->user)->post(route('dashboard.business.store'), $payload)->assertRedirect(route('dashboard'));
        $this->actingAs($this->user)->post(route('dashboard.business.store'), $payload)->assertRedirect(route('dashboard'));

        $business = Business::query()->sole();

        expect($business->slug)->toBe('cafeneaua-verde')
            ->and(session('current_business_id'))->toBe($business->id);
    });

    it('creates two businesses from two separate forms', function () {
        $this->actingAs($this->user)->post(route('dashboard.business.store'), [...$this->payload, 'idempotency_key' => (string) Str::uuid()]);
        $this->actingAs($this->user)->post(route('dashboard.business.store'), [...$this->payload, 'idempotency_key' => (string) Str::uuid()]);

        expect(Business::query()->count())->toBe(2);
    });

    it('does not share keys between users', function () {
        $key = (string) Str::uuid();

        $this->actingAs($this->user)->post(route('dashboard.business.store'), [...$this->payload, 'idempotency_key' => $key]);
        $this->actingAs(User::factory()->create())->post(route('dashboard.business.store'), [...$this->payload, 'idempotency_key' => $key]);

        expect(Business::query()->count())->toBe(2);
    });

    it('lands on the created business when a form with its own slug is submitted twice', function () {
        $payload = [...$this->payload, 'slug' => 'cafe-verde', 'idempotency_key' => (string) Str::uuid()];

        $this->actingAs($this->user)->post(route('dashboard.business.store'), $payload)->assertRedirect(route('dashboard'));
        $this->actingAs($this->user)->post(route('dashboard.business.store'), $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard'));

        expect(Business::query()->sole()->slug)->toBe('cafe-verde');
    });

    it('still refuses a slug taken by another business', function () {
        Business::factory()->create(['slug' => 'cafe-verde']);

        $this->actingAs($this->user)
            ->post(route('dashboard.business.store'), [...$this->payload, 'slug' => 'cafe-verde', 'idempotency_key' => (string) Str::uuid()])
            ->assertSessionHasErrors('slug');
    });

    it('requires the key', function () {
        $this->actingAs($this->user)
            ->post(route('dashboard.business.store'), $this->payload)
            ->assertSessionHasErrors('idempotency_key');

        expect(Business::query()->count())->toBe(0);
    });

    it('explains a stale form and gives it a fresh key', function () {
        $this->actingAs($this->user)
            ->from(route('dashboard.business.create'))
            ->followingRedirects()
            ->post(route('dashboard.business.store'), [...$this->payload, 'idempotency_key' => 'nu-e-uuid'])
            ->assertSee('Formularul nu mai e valabil')
            ->assertDontSee('value="nu-e-uuid"', false);
    });

    it('asks to wait instead of failing while the same form is still being created', function () {
        $key = (string) Str::uuid();
        app()->instance(IdempotentCreation::class, new IdempotentCreation(waitSeconds: 0));
        $lock = Cache::lock("idempotency:business:{$this->user->id}:{$key}:lock", 30);
        $lock->get();

        $this->actingAs($this->user)
            ->post(route('dashboard.business.store'), [...$this->payload, 'idempotency_key' => $key])
            ->assertSessionHasErrors('idempotency_key');

        $lock->release();
        expect(Business::query()->count())->toBe(0);
    });

    it('puts a key in the form', function () {
        $this->actingAs($this->user)
            ->get(route('dashboard.business.create'))
            ->assertSee('name="idempotency_key"', false);
    });
});

describe('location', function () {
    beforeEach(function () {
        $this->business = Business::factory()->create();
        $this->owner = User::factory()->withRole($this->business, Role::Owner)->create();
    });

    it('creates the location once when the same form is submitted twice', function () {
        $payload = ['name' => 'Centru', 'idempotency_key' => (string) Str::uuid()];

        $this->actingAs($this->owner)->post(route('dashboard.locations.store'), $payload)->assertRedirect(route('dashboard.locations.index'));
        $this->actingAs($this->owner)->post(route('dashboard.locations.store'), $payload)->assertRedirect(route('dashboard.locations.index'));

        expect(Location::query()->count())->toBe(1);
    });

    it('requires the key only when creating', function () {
        $location = Location::factory()->for($this->business)->create();

        $this->actingAs($this->owner)
            ->post(route('dashboard.locations.store'), ['name' => 'Centru'])
            ->assertSessionHasErrors('idempotency_key');

        $this->actingAs($this->owner)
            ->patch(route('dashboard.locations.update', $location), ['name' => 'Gară'])
            ->assertSessionHasNoErrors();

        expect($location->fresh()->name)->toBe('Gară');
    });

    it('puts a key in the form', function () {
        $this->actingAs($this->owner)
            ->get(route('dashboard.locations.create'))
            ->assertSee('name="idempotency_key"', false);
    });
});

describe('program', function () {
    beforeEach(function () {
        $this->business = Business::factory()->create(['type' => BusinessType::Cafe]);
        $this->owner = User::factory()->withRole($this->business, Role::Owner)->create();
        $this->payload = [
            'template' => 'cafe',
            ...ProgramTemplates::for(BusinessType::Cafe)->attributes,
            'rollover_extra' => '0',
            'intent' => 'publish',
        ];
    });

    it('creates the program once when the same form is submitted twice', function () {
        $payload = [...$this->payload, 'idempotency_key' => (string) Str::uuid()];

        $this->actingAs($this->owner)->post(route('dashboard.programs.store'), $payload)->assertRedirect(route('dashboard.programs.index'));
        $this->actingAs($this->owner)->post(route('dashboard.programs.store'), $payload)->assertRedirect(route('dashboard.programs.index'));

        expect(Program::query()->count())->toBe(1);
    });

    it('requires the key', function () {
        $this->actingAs($this->owner)
            ->post(route('dashboard.programs.store'), $this->payload)
            ->assertSessionHasErrors('idempotency_key');

        expect(Program::query()->count())->toBe(0);
    });

    it('puts a key in the wizard', function () {
        $this->actingAs($this->owner)
            ->get(route('dashboard.programs.create'))
            ->assertSee('name="idempotency_key"', false);
    });
});
