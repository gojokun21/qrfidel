<?php

use App\Enums\Role;
use App\Exceptions\TenancyViolation;
use App\Models\Business;
use App\Models\Card;
use App\Models\Customer;
use App\Models\Location;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('gives every card a ULID public id and a random secret', function () {
    [$a, $b] = Card::factory()->count(2)->create();

    expect(Str::isUlid($a->public_id))->toBeTrue()
        ->and($a->public_id)->not->toBe($b->public_id)
        ->and($a->secret)->toHaveLength(64)
        ->and($a->secret)->not->toBe($b->secret)
        ->and($a->getRouteKeyName())->toBe('public_id');
});

it('stores the card secret encrypted and never serializes it', function () {
    $card = Card::factory()->create();
    $raw = DB::table('cards')->where('id', $card->id)->value('secret');

    expect($raw)->not->toBe($card->secret)
        ->and(Crypt::decryptString($raw))->toBe($card->secret)
        ->and($card->toArray())->not->toHaveKey('secret');
});

it('starts cards with an empty balance', function () {
    $card = Card::factory()->create()->fresh();

    expect($card->stamps_balance)->toBe(0)
        ->and($card->rewards_available)->toBe(0)
        ->and($card->last_stamp_at)->toBeNull();
});

it('gives every location a unique counter token', function () {
    [$a, $b] = Location::factory()->count(2)->create();

    expect($a->counter_token)->toHaveLength(40)
        ->and($a->counter_token)->not->toBe($b->counter_token);
});

it('normalizes customer emails to lowercase', function () {
    expect(Customer::factory()->create(['email' => ' Ana@Example.COM '])->email)->toBe('ana@example.com');
});

it('orders roles from owner down to staff', function () {
    expect(Role::Owner->atLeast(Role::Manager))->toBeTrue()
        ->and(Role::Manager->atLeast(Role::Manager))->toBeTrue()
        ->and(Role::Manager->atLeast(Role::Owner))->toBeFalse()
        ->and(Role::Staff->atLeast(Role::Manager))->toBeFalse()
        ->and(Role::Staff->atLeast(Role::Staff))->toBeTrue();
});

it('knows the role of a user in each business', function () {
    [$a, $b, $c] = Business::factory()->count(3)->create();
    $location = Location::factory()->for($b)->create();
    $user = User::factory()->withRole($a, Role::Owner)->withRole($b, Role::Manager, $location)->create();

    expect($user->roleIn($a))->toBe(Role::Owner)
        ->and($user->roleIn($b->id))->toBe(Role::Manager)
        ->and($user->roleIn($c))->toBeNull()
        ->and($user->isMemberOf($b))->toBeTrue()
        ->and($user->isMemberOf($c))->toBeFalse()
        ->and($user->membershipIn($b)?->location_id)->toBe($location->id);
});

it('does not let a user be a member of the same business twice', function () {
    $business = Business::factory()->create();

    inSavepoint(fn () => User::factory()->withRole($business, Role::Staff)->withRole($business, Role::Owner)->create());
})->throws(QueryException::class);

it('refuses a membership location from another business', function () {
    [$a, $b] = Business::factory()->count(2)->create();

    User::factory()->withRole($a, Role::Manager, Location::factory()->for($b)->create())->create();
})->throws(TenancyViolation::class);

it('does not make users platform admins through mass assignment', function () {
    $user = User::create([
        'name' => 'X',
        'email' => 'x@example.com',
        'password' => 'secret-password',
        'is_platform_admin' => true,
    ]);

    expect($user->fresh()->isPlatformAdmin())->toBeFalse();
});
