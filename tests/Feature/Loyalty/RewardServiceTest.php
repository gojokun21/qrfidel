<?php

use App\Enums\BusinessStatus;
use App\Enums\ProgramStatus;
use App\Enums\RewardStatus;
use App\Enums\Role;
use App\Enums\TransactionType;
use App\Exceptions\IdempotencyConflict;
use App\Exceptions\RewardUnavailable;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Card;
use App\Models\CardTransaction;
use App\Models\Reward;
use App\Models\User;
use App\Services\Loyalty\RedeemResult;
use App\Services\Loyalty\RewardService;
use App\Services\Loyalty\StampService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

beforeEach(function () {
    foreach (loyaltyFixture(['stamps_required' => 1]) as $key => $value) {
        $this->{$key} = $value;
    }

    // Fiecare ștampilă emite o recompensă.
    $this->earnReward = fn (?Card $card = null): Reward => app(StampService::class)->add(
        card: $card ?? $this->card,
        staff: $this->staff,
        location: $this->location,
        quantity: 1,
        idempotencyKey: (string) Str::ulid(),
    )->rewardsIssued[0];

    $this->redeem = fn (?string $key = null, ?Card $card = null, ?User $staff = null): RedeemResult => app(RewardService::class)->redeem(
        card: $card ?? $this->card,
        staff: $staff ?? $this->staff,
        location: $this->location,
        idempotencyKey: $key ?? (string) Str::ulid(),
    );
});

afterEach(function () {
    Carbon::setTestNow();
});

it('redeems the oldest available reward and records the transaction', function () {
    Carbon::setTestNow('2026-10-15 10:00:00');
    $oldest = ($this->earnReward)();
    Carbon::setTestNow('2026-10-15 11:00:00');
    ($this->earnReward)();

    $result = ($this->redeem)('redeem-1');

    expect($result->replayed)->toBeFalse()
        ->and($result->reward->id)->toBe($oldest->id)
        ->and($result->reward->status)->toBe(RewardStatus::Redeemed)
        ->and($result->reward->redeemed_by)->toBe($this->staff->id)
        ->and($result->reward->location_id)->toBe($this->location->id)
        ->and($result->reward->redeemed_at->toDateTimeString())->toBe('2026-10-15 11:00:00')
        ->and($result->transaction->type)->toBe(TransactionType::Redeem)
        ->and($result->transaction->quantity)->toBe(1)
        ->and($result->transaction->idempotency_key)->toBe('redeem-1')
        ->and($result->transaction->meta)->toMatchArray(['reward_id' => $oldest->id])
        ->and($result->card->rewards_available)->toBe(1);
});

it('fails the second redeem when only one reward was available', function () {
    ($this->earnReward)();
    ($this->redeem)();

    expect(fn () => ($this->redeem)())
        ->toThrow(RewardUnavailable::class, 'Nicio recompensă disponibilă pe acest card.');

    expect(CardTransaction::where('type', TransactionType::Redeem)->count())->toBe(1)
        ->and($this->card->fresh()->rewards_available)->toBe(0);
});

it('does not use a reward twice for the same idempotency key', function () {
    ($this->earnReward)();
    ($this->earnReward)();

    $first = ($this->redeem)('same');
    $second = ($this->redeem)('same');

    expect($second->replayed)->toBeTrue()
        ->and($second->reward->id)->toBe($first->reward->id)
        ->and($second->transaction->id)->toBe($first->transaction->id)
        ->and(Reward::where('status', RewardStatus::Redeemed)->count())->toBe(1)
        ->and($second->card->rewards_available)->toBe(1);
});

it('rejects an idempotency key already used by another action', function () {
    app(StampService::class)->add($this->card, $this->staff, $this->location, 1, 'used-for-stamp');

    expect(fn () => ($this->redeem)('used-for-stamp'))->toThrow(IdempotencyConflict::class);
});

it('skips expired rewards', function () {
    $this->program->update(['reward_expire_days' => 7]);
    Carbon::setTestNow('2026-10-01 10:00:00');
    ($this->earnReward)();
    Carbon::setTestNow('2026-10-05 10:00:00');
    $fresh = ($this->earnReward)();

    Carbon::setTestNow('2026-10-09 10:00:00'); // prima a expirat pe 8
    expect(($this->redeem)()->reward->id)->toBe($fresh->id);

    expect(fn () => ($this->redeem)())->toThrow(RewardUnavailable::class);
});

it('allows redeeming earned rewards when the program is paused or archived', function (ProgramStatus $status) {
    ($this->earnReward)();
    $this->program->update(['status' => $status]);

    expect(($this->redeem)()->reward->status)->toBe(RewardStatus::Redeemed);
})->with([ProgramStatus::Paused, ProgramStatus::Archived]);

it('refuses to redeem when the business is suspended', function () {
    ($this->earnReward)();
    $this->business->status = BusinessStatus::Suspended;
    $this->business->save();

    expect(fn () => ($this->redeem)())->toThrow(RewardUnavailable::class, 'Afacerea este suspendată.');
});

it('refuses staff of another business', function () {
    ($this->earnReward)();
    $foreign = User::factory()->withRole(Business::factory()->create(), Role::Owner)->create();

    expect(fn () => ($this->redeem)(staff: $foreign))->toThrow(AuthorizationException::class)
        ->and(Reward::where('status', RewardStatus::Redeemed)->count())->toBe(0);
});

it('writes an audit log entry', function () {
    ($this->earnReward)();
    $result = ($this->redeem)();

    $log = AuditLog::where('action', 'reward.redeemed')->sole();
    expect($log->subject_id)->toBe($result->transaction->id)
        ->and($log->actor_id)->toBe($this->staff->id)
        ->and($log->payload)->toMatchArray(['card_id' => $this->card->id, 'reward_id' => $result->reward->id]);
});
