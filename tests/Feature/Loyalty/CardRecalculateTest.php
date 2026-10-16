<?php

use App\Enums\RewardStatus;
use App\Enums\TransactionType;
use App\Models\AuditLog;
use App\Models\Card;
use App\Models\CardTransaction;
use App\Models\Reward;
use App\Services\Loyalty\ExpirationService;
use App\Services\Loyalty\StampService;
use App\Services\Loyalty\VoidService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

beforeEach(function () {
    foreach (loyaltyFixture(['stamps_required' => 5, 'max_stamps_per_visit' => 5]) as $key => $value) {
        $this->{$key} = $value;
    }

    Carbon::setTestNow('2026-10-15 10:00:00');

    $this->stamp = fn (int $quantity = 1, ?Card $card = null) => app(StampService::class)->add(
        card: $card ?? $this->card,
        staff: $this->staff,
        location: $this->location,
        quantity: $quantity,
        idempotencyKey: (string) Str::ulid(),
    );

    // Strică cache-ul de pe card, ca un bug sau o editare manuală.
    $this->corrupt = fn (?Card $card = null) => ($card ?? $this->card)->fresh()
        ->forceFill(['stamps_balance' => 4, 'rewards_available' => 3, 'last_stamp_at' => null])
        ->save();
});

afterEach(function () {
    Carbon::setTestNow();
});

it('rebuilds the cached balances from the journal', function () {
    ($this->stamp)(3);
    Carbon::setTestNow(now()->addHour());
    ($this->stamp)(4); // 7 din 5 → o recompensă, soldul 0 (fără rollover)
    $expected = $this->card->fresh();
    ($this->corrupt)();

    $this->artisan('cards:recalculate')
        ->expectsOutputToContain('Carduri verificate: 1, corectate: 1')
        ->assertSuccessful();

    $card = $this->card->fresh();

    expect($card->stamps_balance)->toBe($expected->stamps_balance)
        ->and($card->rewards_available)->toBe(1)
        ->and($card->last_stamp_at->equalTo($expected->last_stamp_at))->toBeTrue();
});

it('follows voids, expiries and rollover', function () {
    $this->program->update(['rollover_extra' => true, 'stamps_expire_days' => 30]);

    ($this->stamp)(4);
    Carbon::setTestNow(now()->addHour());
    ($this->stamp)(3); // 7 → recompensă, sold 2
    Carbon::setTestNow(now()->addHour());
    $mistake = ($this->stamp)(1)->transaction;
    app(VoidService::class)->void($mistake, $this->manager, (string) Str::ulid());

    expect($this->card->fresh()->stamps_balance)->toBe(2);
    ($this->corrupt)();

    $this->artisan('cards:recalculate')->assertSuccessful();
    expect($this->card->fresh()->stamps_balance)->toBe(2)
        ->and($this->card->fresh()->rewards_available)->toBe(1);

    Carbon::setTestNow(now()->addDays(31));
    app(ExpirationService::class)->expireStamps();
    ($this->corrupt)();

    $this->artisan('cards:recalculate')->assertSuccessful();
    expect($this->card->fresh()->stamps_balance)->toBe(0);
});

it('counts only available rewards', function () {
    giveReward($this->card);
    Reward::factory()->for($this->card)->create(['status' => RewardStatus::Redeemed]);
    Reward::factory()->for($this->card)->create(['status' => RewardStatus::Voided]);
    ($this->corrupt)();

    $this->artisan('cards:recalculate')->assertSuccessful();

    expect($this->card->fresh()->rewards_available)->toBe(1);
});

it('replays stamps that have no balance metadata (seed data)', function () {
    foreach ([2, 2, 3] as $quantity) {
        CardTransaction::factory()->for($this->card)->create([
            'quantity' => $quantity,
            'location_id' => $this->location->id,
            'staff_user_id' => $this->staff->id,
        ]);
    }

    $this->artisan('cards:recalculate')->assertSuccessful();

    // 2 + 2 + 3 = 7 → o recompensă câștigată, surplusul se pierde (fără rollover)
    expect($this->card->fresh()->stamps_balance)->toBe(0)
        ->and($this->card->fresh()->last_stamp_at)->not->toBeNull();
});

it('only reports differences with --dry-run', function () {
    ($this->stamp)(2);
    ($this->corrupt)();

    $this->artisan('cards:recalculate', ['--dry-run' => true])
        ->expectsOutputToContain('Carduri verificate: 1, de corectat: 1')
        ->assertSuccessful();

    expect($this->card->fresh()->stamps_balance)->toBe(4)
        ->and(AuditLog::query()->where('action', 'card.recalculated')->exists())->toBeFalse();
});

it('leaves correct cards untouched and audits only the corrected ones', function () {
    ($this->stamp)(2);
    $other = Card::factory()->for($this->program)->create();
    ($this->stamp)(1, $other);
    ($this->corrupt)($other);

    $this->artisan('cards:recalculate')
        ->expectsOutputToContain('Carduri verificate: 2, corectate: 1')
        ->assertSuccessful();

    $log = AuditLog::query()->where('action', 'card.recalculated')->sole();

    expect($log->actor_id)->toBeNull()
        ->and($log->payload['card_id'])->toBe($other->id)
        ->and($log->payload['before'])->toMatchArray(['stamps_balance' => 4, 'rewards_available' => 3, 'last_stamp_at' => null])
        ->and($log->payload['after'])->toMatchArray(['stamps_balance' => 1, 'rewards_available' => 0]);
});

it('filters by card and by business', function () {
    ($this->stamp)(2);
    ($this->corrupt)();

    $foreign = loyaltyFixture();
    $foreign['card']->forceFill(['stamps_balance' => 9])->save();

    $this->artisan('cards:recalculate', ['--card' => $this->card->public_id])
        ->expectsOutputToContain('Carduri verificate: 1, corectate: 1')
        ->assertSuccessful();
    expect($foreign['card']->fresh()->stamps_balance)->toBe(9);

    $this->artisan('cards:recalculate', ['--business' => $foreign['business']->id])
        ->expectsOutputToContain('Carduri verificate: 1, corectate: 1')
        ->assertSuccessful();
    expect($foreign['card']->fresh()->stamps_balance)->toBe(0);
});

it('fails on an unknown card', function () {
    $this->artisan('cards:recalculate', ['--card' => 'nope'])->assertFailed();
});

it('fails on a non-numeric business instead of recalculating every business', function () {
    ($this->stamp)(2);
    ($this->corrupt)();

    $this->artisan('cards:recalculate', ['--business' => 'cafe-luna'])->assertFailed();

    expect($this->card->fresh()->stamps_balance)->toBe(4);
});

it('keeps the journal append-only', function () {
    ($this->stamp)(2);
    ($this->corrupt)();
    $before = CardTransaction::query()->count();

    $this->artisan('cards:recalculate')->assertSuccessful();

    expect(CardTransaction::query()->count())->toBe($before)
        ->and(CardTransaction::query()->where('type', TransactionType::Adjust)->exists())->toBeFalse();
});
