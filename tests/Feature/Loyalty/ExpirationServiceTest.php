<?php

use App\Enums\BusinessStatus;
use App\Enums\RewardStatus;
use App\Enums\StampRequestStatus;
use App\Enums\TransactionType;
use App\Exceptions\VoidNotAllowed;
use App\Models\AuditLog;
use App\Models\Card;
use App\Models\CardTransaction;
use App\Models\Reward;
use App\Models\StampRequest;
use App\Services\Loyalty\ExpirationService;
use App\Services\Loyalty\RewardService;
use App\Services\Loyalty\StampService;
use App\Services\Loyalty\VoidService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

beforeEach(function () {
    foreach (loyaltyFixture(['stamps_required' => 5, 'max_stamps_per_visit' => 3, 'stamps_expire_days' => 30, 'reward_expire_days' => 10]) as $key => $value) {
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

    $this->expirations = fn (): ExpirationService => app(ExpirationService::class);
    $this->transactions = fn (?Card $card = null) => CardTransaction::query()->where('card_id', ($card ?? $this->card)->id);
});

afterEach(function () {
    Carbon::setTestNow();
});

describe('stamps expire after inactivity', function () {
    it('resets the balance with an expire transaction once stamps_expire_days pass without a stamp', function () {
        ($this->stamp)(3);
        Carbon::setTestNow(now()->addDays(30));

        $expired = ($this->expirations)()->expireStamps();

        $card = $this->card->fresh();
        $expire = ($this->transactions)()->where('type', TransactionType::Expire)->sole();

        expect($expired)->toBe(1)
            ->and($card->stamps_balance)->toBe(0)
            ->and($expire->quantity)->toBe(-3)
            ->and($expire->staff_user_id)->toBeNull()
            ->and($expire->source)->toBeNull()
            ->and($expire->meta)->toMatchArray(['balance_before' => 3, 'balance_after' => 0])
            ->and($card->last_stamp_at->equalTo(now()->subDays(30)))->toBeTrue();
    });

    it('records the expiry in the audit log as a system action', function () {
        ($this->stamp)(2);
        Carbon::setTestNow(now()->addDays(31));

        ($this->expirations)()->expireStamps();

        $log = AuditLog::query()->where('action', 'card.stamps_expired')->sole();

        expect($log->actor_type)->toBeNull()
            ->and($log->actor_id)->toBeNull()
            ->and($log->business_id)->toBe($this->business->id)
            ->and($log->payload)->toMatchArray(['card_id' => $this->card->id, 'quantity' => 2]);
    });

    it('leaves cards that are still active, have no balance or belong to programs without expiry', function () {
        ($this->stamp)(2); // ultima ștampilă acum 29 de zile → încă activ

        $empty = Card::factory()->for($this->program)->create(['last_stamp_at' => now()->subDays(90)]);

        $noExpiry = loyaltyFixture(['stamps_expire_days' => null])['card'];
        $noExpiry->forceFill(['stamps_balance' => 2, 'last_stamp_at' => now()->subYear()])->save();

        Carbon::setTestNow(now()->addDays(29));

        expect(($this->expirations)()->expireStamps())->toBe(0)
            ->and($this->card->fresh()->stamps_balance)->toBe(2)
            ->and($empty->fresh()->stamps_balance)->toBe(0)
            ->and($noExpiry->fresh()->stamps_balance)->toBe(2)
            ->and(CardTransaction::query()->where('type', TransactionType::Expire)->exists())->toBeFalse();
    });

    it('expires exactly when stamps_expire_days have passed', function () {
        ($this->stamp)(1);
        Carbon::setTestNow(now()->addDays(30)->subSecond());

        expect(($this->expirations)()->expireStamps())->toBe(0);

        Carbon::setTestNow(now()->addSecond());

        expect(($this->expirations)()->expireStamps())->toBe(1);
    });

    it('skips suspended businesses', function () {
        ($this->stamp)(2);
        $this->business->forceFill(['status' => BusinessStatus::Suspended])->save();
        Carbon::setTestNow(now()->addDays(40));

        expect(($this->expirations)()->expireStamps())->toBe(0)
            ->and($this->card->fresh()->stamps_balance)->toBe(2);
    });

    it('expires a card only once when the job runs again', function () {
        ($this->stamp)(2);
        Carbon::setTestNow(now()->addDays(40));

        ($this->expirations)()->expireStamps();
        ($this->expirations)()->expireStamps();

        expect(($this->transactions)()->where('type', TransactionType::Expire)->count())->toBe(1);
    });

    it('ignores voided stamps when it decides the card is inactive', function () {
        ($this->stamp)(1);
        Carbon::setTestNow(now()->addDays(20));
        $late = ($this->stamp)(1)->transaction;
        app(VoidService::class)->void($late, $this->manager, (string) Str::ulid());

        // void-ul a mutat last_stamp_at înapoi pe prima ștampilă, de acum 20 de zile
        Carbon::setTestNow(now()->addDays(10));

        expect(($this->expirations)()->expireStamps())->toBe(1)
            ->and($this->card->fresh()->stamps_balance)->toBe(0);
    });

    it('blocks voiding a stamp that has expired since', function () {
        // La limită (1 zi) ștampila e încă în fereastra de 24h a void-ului: regula „ultima ștampilă” o oprește.
        $this->program->update(['stamps_expire_days' => 1]);
        $stamp = ($this->stamp)(2)->transaction;
        Carbon::setTestNow(now()->addDay());

        ($this->expirations)()->expireStamps();

        app(VoidService::class)->void($stamp, $this->owner, (string) Str::ulid());
    })->throws(VoidNotAllowed::class, 'Doar ultima ștampilă a cardului poate fi anulată.');
});

describe('rewards expire', function () {
    it('marks expired rewards and lowers the cached rewards_available', function () {
        $old = giveReward($this->card, now()->subMinute());
        $alsoOld = giveReward($this->card, now());
        $future = giveReward($this->card, now()->addDay());
        $forever = giveReward($this->card);

        $expired = ($this->expirations)()->expireRewards();

        expect($expired)->toBe(2)
            ->and($old->fresh()->status)->toBe(RewardStatus::Expired)
            ->and($alsoOld->fresh()->status)->toBe(RewardStatus::Expired)
            ->and($future->fresh()->status)->toBe(RewardStatus::Available)
            ->and($forever->fresh()->status)->toBe(RewardStatus::Available)
            ->and($this->card->fresh()->rewards_available)->toBe(2)
            ->and(CardTransaction::query()->where('type', TransactionType::Expire)->exists())->toBeFalse();
    });

    it('records one audit entry per card with the expired rewards', function () {
        $reward = giveReward($this->card, now()->subMinute());

        ($this->expirations)()->expireRewards();

        $log = AuditLog::query()->where('action', 'reward.expired')->sole();

        expect($log->actor_id)->toBeNull()
            ->and($log->payload)->toMatchArray(['card_id' => $this->card->id, 'count' => 1, 'reward_ids' => [$reward->id]]);
    });

    it('does not touch redeemed or voided rewards', function () {
        $redeemed = Reward::factory()->for($this->card)->create(['status' => RewardStatus::Redeemed, 'expires_at' => now()->subDay()]);
        $voided = Reward::factory()->for($this->card)->create(['status' => RewardStatus::Voided, 'expires_at' => now()->subDay()]);

        expect(($this->expirations)()->expireRewards())->toBe(0)
            ->and($redeemed->fresh()->status)->toBe(RewardStatus::Redeemed)
            ->and($voided->fresh()->status)->toBe(RewardStatus::Voided)
            ->and($this->card->fresh()->rewards_available)->toBe(0);
    });

    it('is idempotent', function () {
        giveReward($this->card, now()->subMinute());

        ($this->expirations)()->expireRewards();

        expect(($this->expirations)()->expireRewards())->toBe(0)
            ->and($this->card->fresh()->rewards_available)->toBe(0);
    });

    it('keeps a voided redeem consistent after the reward expired', function () {
        giveReward($this->card, now()->addHour());
        $redeem = app(RewardService::class)->redeem($this->card, $this->staff, $this->location, (string) Str::ulid())->transaction;
        Carbon::setTestNow(now()->addHours(2));

        app(VoidService::class)->void($redeem, $this->manager, (string) Str::ulid());
        ($this->expirations)()->expireRewards();

        expect($this->card->fresh()->rewards_available)->toBe(0)
            ->and(Reward::query()->where('card_id', $this->card->id)->sole()->status)->toBe(RewardStatus::Expired);
    });
});

describe('stamp requests expire', function () {
    it('marks pending requests past their deadline as expired', function () {
        $late = StampRequest::factory()->for($this->card)->create(['location_id' => $this->location->id, 'expires_at' => now()->subSecond()]);
        $other = Card::factory()->for($this->program)->create();
        $open = StampRequest::factory()->for($other)->create(['location_id' => $this->location->id, 'expires_at' => now()->addMinute()]);
        $third = Card::factory()->for($this->program)->create();
        $rejected = StampRequest::factory()->for($third)->create([
            'location_id' => $this->location->id,
            'status' => StampRequestStatus::Rejected,
            'expires_at' => now()->subHour(),
        ]);

        expect(($this->expirations)()->expireStampRequests())->toBe(1)
            ->and($late->fresh()->status)->toBe(StampRequestStatus::Expired)
            ->and($open->fresh()->status)->toBe(StampRequestStatus::Pending)
            ->and($rejected->fresh()->status)->toBe(StampRequestStatus::Rejected);
    });
});
