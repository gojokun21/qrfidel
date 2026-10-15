<?php

use App\Enums\RewardStatus;
use App\Enums\Role;
use App\Enums\TransactionType;
use App\Exceptions\VoidNotAllowed;
use App\Models\AuditLog;
use App\Models\Card;
use App\Models\CardTransaction;
use App\Models\Reward;
use App\Models\User;
use App\Services\Loyalty\RewardService;
use App\Services\Loyalty\StampResult;
use App\Services\Loyalty\StampService;
use App\Services\Loyalty\VoidResult;
use App\Services\Loyalty\VoidService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

beforeEach(function () {
    foreach (loyaltyFixture(['max_stamps_per_visit' => 5]) as $key => $value) {
        $this->{$key} = $value;
    }

    Carbon::setTestNow('2026-10-15 10:00:00');

    $this->stamp = fn (int $quantity = 1, ?Card $card = null): StampResult => app(StampService::class)->add(
        card: $card ?? $this->card,
        staff: $this->staff,
        location: $this->location,
        quantity: $quantity,
        idempotencyKey: (string) Str::ulid(),
    );

    $this->redeem = fn () => app(RewardService::class)->redeem(
        card: $this->card,
        staff: $this->staff,
        location: $this->location,
        idempotencyKey: (string) Str::ulid(),
    );

    $this->void = fn (CardTransaction $transaction, ?User $actor = null, ?string $key = null): VoidResult => app(VoidService::class)->void(
        transaction: $transaction,
        actor: $actor ?? $this->manager,
        idempotencyKey: $key ?? (string) Str::ulid(),
        reason: 'greșeală',
    );
});

afterEach(function () {
    Carbon::setTestNow();
});

describe('voiding stamps', function () {
    it('creates an inverse transaction, marks the original and restores the balance', function () {
        ($this->stamp)(2);
        $mistake = ($this->stamp)(1)->transaction;

        $result = ($this->void)($mistake, key: 'void-1');

        expect($result->replayed)->toBeFalse()
            ->and($result->voidTransaction->type)->toBe(TransactionType::Void)
            ->and($result->voidTransaction->quantity)->toBe(-1)
            ->and($result->voidTransaction->staff_user_id)->toBe($this->manager->id)
            ->and($result->voidTransaction->idempotency_key)->toBe('void-1')
            ->and($result->voidTransaction->meta)->toMatchArray(['voided_transaction_id' => $mistake->id, 'reason' => 'greșeală'])
            ->and($mistake->fresh()->voided_by_id)->toBe($result->voidTransaction->id)
            ->and($result->card->stamps_balance)->toBe(2)
            ->and(CardTransaction::where('card_id', $this->card->id)->count())->toBe(3);
    });

    it('revokes the reward issued by the voided stamp', function (bool $rollover) {
        $this->program->update(['rollover_extra' => $rollover]);
        ($this->stamp)(4);
        $crossing = ($this->stamp)(2); // 6 din 5 → recompensă

        $result = ($this->void)($crossing->transaction);

        expect($result->card->stamps_balance)->toBe(4)
            ->and($result->card->rewards_available)->toBe(0)
            ->and($crossing->rewardsIssued[0]->fresh()->status)->toBe(RewardStatus::Voided);
    })->with(['without rollover' => false, 'with rollover' => true]);

    it('refuses to void a stamp whose reward was already redeemed', function () {
        ($this->stamp)(4);
        $crossing = ($this->stamp)(1);
        ($this->redeem)();

        expect(fn () => ($this->void)($crossing->transaction))
            ->toThrow(VoidNotAllowed::class, 'Recompensa emisă de această ștampilă a fost deja folosită.');

        expect($crossing->transaction->fresh()->voided_by_id)->toBeNull();
    });

    it('only voids the latest active stamp of the card', function () {
        $first = ($this->stamp)(1)->transaction;
        ($this->stamp)(1);

        expect(fn () => ($this->void)($first))
            ->toThrow(VoidNotAllowed::class, 'Doar ultima ștampilă a cardului poate fi anulată.');
    });

    it('allows voiding the previous stamp once the latest one was voided', function () {
        $first = ($this->stamp)(1)->transaction;
        $second = ($this->stamp)(1)->transaction;

        ($this->void)($second);

        expect(($this->void)($first)->card->stamps_balance)->toBe(0);
    });

    it('frees the cooldown and the daily limit', function () {
        $this->program->update(['cooldown_minutes' => 30, 'daily_limit' => 1]);
        Carbon::setTestNow('2026-10-15 08:00:00');
        $earlier = ($this->stamp)(1)->transaction;
        Carbon::setTestNow('2026-10-16 08:00:00');
        $mistake = ($this->stamp)(1)->transaction;

        ($this->void)($mistake);

        $card = $this->card->fresh();
        expect($card->last_stamp_at->toDateTimeString())->toBe($earlier->created_at->toDateTimeString());

        expect(($this->stamp)(1)->card->stamps_balance)->toBe(2);
    });

    it('clears last_stamp_at when no active stamp remains', function () {
        $only = ($this->stamp)(1)->transaction;

        expect(($this->void)($only)->card->last_stamp_at)->toBeNull();
    });
});

describe('voiding redeems', function () {
    it('makes the reward available again', function () {
        $this->program->update(['stamps_required' => 1]);
        $reward = ($this->stamp)(1)->rewardsIssued[0];
        $redeem = ($this->redeem)()->transaction;

        $result = ($this->void)($redeem);

        $reward->refresh();
        expect($result->voidTransaction->quantity)->toBe(-1)
            ->and($result->card->rewards_available)->toBe(1)
            ->and($reward->status)->toBe(RewardStatus::Available)
            ->and($reward->redeemed_at)->toBeNull()
            ->and($reward->redeemed_by)->toBeNull()
            ->and($reward->location_id)->toBeNull();
    });
});

it('marks the reward expired instead of available when it expired since the redeem', function () {
    $this->program->update(['stamps_required' => 1, 'reward_expire_days' => 1]);
    $reward = ($this->stamp)(1)->rewardsIssued[0];
    Carbon::setTestNow('2026-10-16 09:00:00');
    $redeem = ($this->redeem)()->transaction;

    Carbon::setTestNow('2026-10-16 11:00:00'); // a expirat la 10:00
    $result = ($this->void)($redeem);

    expect($reward->fresh()->status)->toBe(RewardStatus::Expired)
        ->and($result->card->rewards_available)->toBe(0);
});

describe('rules', function () {
    it('refuses after 24 hours', function () {
        $stamp = ($this->stamp)(1)->transaction;

        Carbon::setTestNow('2026-10-16 10:00:01');

        expect(fn () => ($this->void)($stamp))
            ->toThrow(VoidNotAllowed::class, 'Tranzacțiile pot fi anulate doar în primele 24 de ore.');
    });

    it('allows exactly at 24 hours', function () {
        $stamp = ($this->stamp)(1)->transaction;

        Carbon::setTestNow('2026-10-16 10:00:00');

        expect(($this->void)($stamp)->card->stamps_balance)->toBe(0);
    });

    it('refuses a transaction that was already voided', function () {
        $stamp = ($this->stamp)(1)->transaction;
        ($this->void)($stamp);

        expect(fn () => ($this->void)($stamp->fresh()))
            ->toThrow(VoidNotAllowed::class, 'Tranzacția a fost deja anulată.');
    });

    it('refuses to void a void transaction', function () {
        $stamp = ($this->stamp)(1)->transaction;
        $void = ($this->void)($stamp)->voidTransaction;

        expect(fn () => ($this->void)($void))
            ->toThrow(VoidNotAllowed::class, 'Doar ștampilele și recompensele folosite pot fi anulate.');
    });

    it('replays the same idempotency key', function () {
        $stamp = ($this->stamp)(1)->transaction;
        $first = ($this->void)($stamp, key: 'v');

        $second = ($this->void)($stamp->fresh(), key: 'v');

        expect($second->replayed)->toBeTrue()
            ->and($second->voidTransaction->id)->toBe($first->voidTransaction->id)
            ->and(CardTransaction::where('type', TransactionType::Void)->count())->toBe(1);
    });

    it('never deletes transactions', function () {
        $stamp = ($this->stamp)(1)->transaction;
        ($this->void)($stamp);

        expect(CardTransaction::find($stamp->id))->not->toBeNull();
    });

    it('writes an audit log entry', function () {
        $stamp = ($this->stamp)(1)->transaction;
        $result = ($this->void)($stamp);

        $log = AuditLog::where('action', 'transaction.voided')->sole();
        expect($log->actor_id)->toBe($this->manager->id)
            ->and($log->subject_id)->toBe($result->voidTransaction->id)
            ->and($log->payload)->toMatchArray(['voided_transaction_id' => $stamp->id, 'reason' => 'greșeală']);
    });
});

describe('who can void', function () {
    it('refuses staff', function () {
        $stamp = ($this->stamp)(1)->transaction;

        expect(fn () => ($this->void)($stamp, $this->staff))->toThrow(AuthorizationException::class);
    });

    it('refuses a manager of another location', function () {
        $stamp = app(StampService::class)->add($this->card, $this->staff, $this->otherLocation, 1, 'k')->transaction;

        expect(fn () => ($this->void)($stamp))
            ->toThrow(VoidNotAllowed::class, 'Poți anula doar tranzacțiile din locația ta.');
    });

    it('lets a manager without a location and the owner void anywhere', function (string $who) {
        $actor = $who === 'owner'
            ? $this->owner
            : User::factory()->withRole($this->business, Role::Manager)->create();
        $stamp = app(StampService::class)->add($this->card, $this->staff, $this->otherLocation, 1, 'k')->transaction;

        expect(($this->void)($stamp, $actor)->card->stamps_balance)->toBe(0);
    })->with(['owner', 'unassigned manager']);

    it('refuses managers of another business', function () {
        $other = loyaltyFixture();
        $stamp = ($this->stamp)(1)->transaction;

        expect(fn () => ($this->void)($stamp, $other['owner']))->toThrow(AuthorizationException::class);
    });
});

it('keeps rewards status values consistent with the database', function () {
    $reward = Reward::factory()->create(['status' => RewardStatus::Voided]);

    expect($reward->fresh()->status)->toBe(RewardStatus::Voided);
});
