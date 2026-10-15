<?php

use App\Enums\BusinessType;
use App\Enums\ProgramStatus;
use App\Enums\RewardStatus;
use App\Enums\Role;
use App\Enums\TransactionType;
use App\Models\Business;
use App\Models\Card;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

it('creates the two demo businesses with active programs', function () {
    $cafe = Business::where('slug', 'cafenea-demo')->firstOrFail();
    $carwash = Business::where('slug', 'spalatorie-demo')->firstOrFail();

    expect($cafe->type)->toBe(BusinessType::Cafe)
        ->and($carwash->type)->toBe(BusinessType::Carwash)
        ->and($cafe->locations()->count())->toBe(2)
        ->and($carwash->locations()->count())->toBe(1);

    $cafeProgram = $cafe->programs()->sole();
    $carwashProgram = $carwash->programs()->sole();

    expect($cafeProgram->status)->toBe(ProgramStatus::Active)
        ->and($cafeProgram->stamps_required)->toBe(5)
        ->and($cafeProgram->cooldown_minutes)->toBe(0)
        ->and($carwashProgram->status)->toBe(ProgramStatus::Active)
        ->and($carwashProgram->stamps_required)->toBe(10)
        ->and($carwashProgram->daily_limit)->toBe(1);
});

it('creates the demo accounts with their roles', function () {
    $cafe = Business::where('slug', 'cafenea-demo')->firstOrFail();
    $carwash = Business::where('slug', 'spalatorie-demo')->firstOrFail();

    $user = fn (string $email) => User::where('email', $email)->firstOrFail();

    expect($user('admin@demo.test')->isPlatformAdmin())->toBeTrue()
        ->and($user('owner@cafenea.test')->roleIn($cafe))->toBe(Role::Owner)
        ->and($user('manager@cafenea.test')->roleIn($cafe))->toBe(Role::Manager)
        ->and($user('manager@cafenea.test')->membershipIn($cafe)?->location_id)->not->toBeNull()
        ->and($user('staff@cafenea.test')->roleIn($cafe))->toBe(Role::Staff)
        ->and($user('staff@cafenea.test')->roleIn($carwash))->toBeNull()
        ->and($user('owner@spalatorie.test')->roleIn($carwash))->toBe(Role::Owner)
        ->and($user('staff@spalatorie.test')->roleIn($carwash))->toBe(Role::Staff);
});

it('keeps card balances consistent with the transaction journal', function () {
    $cards = Card::with(['program', 'transactions', 'rewards'])->get();

    expect($cards)->not->toBeEmpty();

    foreach ($cards as $card) {
        $stamps = $card->transactions->where('type', TransactionType::Stamp)->sum('quantity');
        $issued = $card->rewards->count();

        expect($card->stamps_balance)->toBe($stamps - $issued * $card->program->stamps_required)
            ->and($card->rewards_available)->toBe($card->rewards->where('status', RewardStatus::Available)->count());
    }
});

it('isolates the demo businesses from each other', function () {
    $cafe = Business::where('slug', 'cafenea-demo')->firstOrFail();
    $carwash = Business::where('slug', 'spalatorie-demo')->firstOrFail();

    inBusiness($cafe);
    $cafeCards = Card::pluck('business_id')->unique()->all();

    inBusiness($carwash);
    $carwashCards = Card::pluck('business_id')->unique()->all();

    expect($cafeCards)->toBe([$cafe->id])
        ->and($carwashCards)->toBe([$carwash->id])
        ->and(User::where('email', 'staff@cafenea.test')->firstOrFail()->can('stamp', Card::firstOrFail()))->toBeFalse();
});

it('refuses to run in production', function () {
    app()->detectEnvironment(fn () => 'production');

    app(DemoSeeder::class)->run();
})->throws(RuntimeException::class);
