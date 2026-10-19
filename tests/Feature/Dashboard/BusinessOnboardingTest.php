<?php

use App\Enums\BusinessStatus;
use App\Enums\BusinessType;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\User;
use App\Services\Loyalty\BusinessService;
use Illuminate\Support\Str;

it('sends a user without a business to the onboarding form', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertRedirect(route('dashboard.business.create'));
});

it('shows the dashboard home to a member', function () {
    $business = Business::factory()->create(['name' => 'Cafeneaua Mea']);
    $user = User::factory()->withRole($business, Role::Staff)->create();

    $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('Cafeneaua Mea');
});

it('requires a verified email to create a business', function () {
    $this->actingAs(User::factory()->unverified()->create())
        ->get(route('dashboard.business.create'))
        ->assertRedirect(route('verification.notice'));
});

it('creates a business and makes the creator its owner', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('dashboard.business.store'), [
            'idempotency_key' => (string) Str::uuid(),
            'name' => 'Cafeneaua Verde',
            'type' => 'cafe',
            'timezone' => 'Europe/Bucharest',
            'brand_color' => '#10B981',
        ])
        ->assertRedirect(route('dashboard'))
        ->assertSessionHasNoErrors();

    $business = Business::query()->sole();

    expect($business->name)->toBe('Cafeneaua Verde')
        ->and($business->slug)->toBe('cafeneaua-verde')
        ->and($business->type)->toBe(BusinessType::Cafe)
        ->and($business->brand_color)->toBe('#10b981')
        ->and($business->status)->toBe(BusinessStatus::Active)
        ->and($user->roleIn($business))->toBe(Role::Owner)
        ->and(session('current_business_id'))->toBe($business->id)
        ->and(AuditLog::query()->where('action', 'business.created')->where('business_id', $business->id)->exists())->toBeTrue();
});

it('generates a unique slug when the name is taken', function () {
    Business::factory()->create(['slug' => 'cafeneaua-verde']);
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('dashboard.business.store'), [
        'idempotency_key' => (string) Str::uuid(),
        'name' => 'Cafeneaua Verde',
        'type' => 'cafe',
        'timezone' => 'Europe/Bucharest',
    ])->assertSessionHasNoErrors();

    expect($user->businesses()->sole()->slug)->toBe('cafeneaua-verde-2');
});

it('accepts a custom slug at creation', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('dashboard.business.store'), [
        'idempotency_key' => (string) Str::uuid(),
        'name' => 'Spălătoria Rapidă',
        'slug' => 'spala-rapid',
        'type' => 'carwash',
        'timezone' => 'Europe/Bucharest',
    ])->assertSessionHasNoErrors();

    expect($user->businesses()->sole()->slug)->toBe('spala-rapid');
});

it('validates the business data', function (array $data, string $field) {
    $user = User::factory()->create();
    Business::factory()->create(['slug' => 'luat']);

    $this->actingAs($user)
        ->post(route('dashboard.business.store'), [
            'idempotency_key' => (string) Str::uuid(),
            'name' => 'Afacere',
            'type' => 'cafe',
            'timezone' => 'Europe/Bucharest',
            ...$data,
        ])
        ->assertSessionHasErrors($field);

    expect(Business::query()->where('name', 'Afacere')->exists())->toBeFalse();
})->with([
    'missing name' => [['name' => ''], 'name'],
    'unknown type' => [['type' => 'bar'], 'type'],
    'bad timezone' => [['timezone' => 'Mars/Base'], 'timezone'],
    'bad color' => [['brand_color' => 'red'], 'brand_color'],
    'bad slug' => [['slug' => 'Nu E Bun!'], 'slug'],
    'taken slug' => [['slug' => 'luat'], 'slug'],
]);

it('does not let the creator set the business status', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('dashboard.business.store'), [
        'idempotency_key' => (string) Str::uuid(),
        'name' => 'Afacere',
        'type' => 'cafe',
        'timezone' => 'Europe/Bucharest',
        'status' => 'suspended',
    ]);

    expect($user->businesses()->sole()->status)->toBe(BusinessStatus::Active);
});

it('lets the owner update the business but not its slug', function () {
    $business = Business::factory()->create(['slug' => 'original']);
    $owner = User::factory()->withRole($business, Role::Owner)->create();

    $this->actingAs($owner)->get(route('dashboard.business.edit'))->assertOk();

    $this->actingAs($owner)
        ->patch(route('dashboard.business.update'), [
            'name' => 'Nume Nou',
            'slug' => 'alt-slug',
            'type' => 'restaurant',
            'timezone' => 'Europe/London',
            'brand_color' => '#123456',
        ])
        ->assertRedirect(route('dashboard.business.edit'))
        ->assertSessionHasNoErrors();

    $business->refresh();

    expect($business->name)->toBe('Nume Nou')
        ->and($business->slug)->toBe('original')
        ->and($business->type)->toBe(BusinessType::Restaurant)
        ->and($business->timezone)->toBe('Europe/London')
        ->and(AuditLog::query()->where('action', 'business.updated')->exists())->toBeTrue();
});

it('forbids managers and staff from editing the business', function (Role $role) {
    $business = Business::factory()->create();
    $user = User::factory()->withRole($business, $role)->create();

    $this->actingAs($user)->get(route('dashboard.business.edit'))->assertForbidden();
    $this->actingAs($user)->patch(route('dashboard.business.update'), [
        'name' => 'X', 'type' => 'cafe', 'timezone' => 'Europe/Bucharest',
    ])->assertForbidden();
})->with([Role::Manager, Role::Staff]);

it('only updates the current business of the owner', function () {
    $mine = Business::factory()->create(['name' => 'A mea']);
    $foreign = Business::factory()->create(['name' => 'Străină']);
    $owner = User::factory()->withRole($mine, Role::Owner)->create();

    $this->actingAs($owner)
        ->withSession(['current_business_id' => $foreign->id])
        ->patch(route('dashboard.business.update'), ['name' => 'Furat', 'type' => 'cafe', 'timezone' => 'Europe/Bucharest']);

    expect($foreign->refresh()->name)->toBe('Străină')
        ->and($mine->refresh()->name)->toBe('Furat');
});

it('switches the current business only to one the user belongs to', function () {
    $first = Business::factory()->create();
    $second = Business::factory()->create();
    $foreign = Business::factory()->create();
    $user = User::factory()->withRole($first, Role::Owner)->withRole($second, Role::Staff)->create();

    $this->actingAs($user)
        ->post(route('dashboard.business.switch'), ['business_id' => $second->id])
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('current_business_id', $second->id);

    $this->actingAs($user)
        ->post(route('dashboard.business.switch'), ['business_id' => $foreign->id])
        ->assertSessionHasErrors('business_id');

    expect(session('current_business_id'))->toBe($second->id);
});

it('never generates a slug ending in a hyphen', function () {
    $slug = app(BusinessService::class)->uniqueSlug(str_repeat('a', 49).' bcd');

    expect($slug)->toBe(str_repeat('a', 49));
});

it('offers a color picker next to the hex field for the brand color', function () {
    $business = Business::factory()->create(['brand_color' => '#7c2d12']);
    $owner = User::factory()->withRole($business, Role::Owner)->create();

    $this->actingAs($owner)->get(route('dashboard.business.edit'))
        ->assertOk()
        ->assertSee('type="color"', false)
        ->assertSee('aria-label="Culoarea #01a760"', false)
        ->assertSee('data-color="#7c2d12"', false)
        ->assertSee('name="brand_color"', false)
        ->assertSee('x-model="color"', false);
});
