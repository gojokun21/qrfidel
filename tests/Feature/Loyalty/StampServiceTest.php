<?php

use App\Enums\BusinessStatus;
use App\Enums\ProgramStatus;
use App\Enums\RewardStatus;
use App\Enums\Role;
use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Exceptions\IdempotencyConflict;
use App\Exceptions\StampRuleViolation;
use App\Exceptions\TenancyViolation;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Card;
use App\Models\CardTransaction;
use App\Models\Location;
use App\Models\Reward;
use App\Models\User;
use App\Services\Loyalty\StampResult;
use App\Services\Loyalty\StampService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

beforeEach(function () {
    foreach (loyaltyFixture() as $key => $value) {
        $this->{$key} = $value;
    }

    $this->stamp = fn (int $quantity = 1, ?string $key = null, ?Card $card = null, ?User $staff = null, ?Location $location = null): StampResult => app(StampService::class)->add(
        card: $card ?? $this->card,
        staff: $staff ?? $this->staff,
        location: $location ?? $this->location,
        quantity: $quantity,
        idempotencyKey: $key ?? (string) Str::ulid(),
    );
});

afterEach(function () {
    Carbon::setTestNow();
});

describe('progress and rewards', function () {
    it('adds stamps and shows progress 3/5', function () {
        foreach (range(1, 3) as $ignored) {
            ($this->stamp)();
        }

        $card = $this->card->fresh();
        expect($card->stamps_balance)->toBe(3)
            ->and($card->rewards_available)->toBe(0)
            ->and($card->last_stamp_at)->not->toBeNull()
            ->and(CardTransaction::where('card_id', $card->id)->where('type', TransactionType::Stamp)->count())->toBe(3);
    });

    it('records who, where, how and the balances on the stamp transaction', function () {
        $result = app(StampService::class)->add(
            card: $this->card,
            staff: $this->staff,
            location: $this->location,
            quantity: 1,
            idempotencyKey: 'key-1',
            source: TransactionSource::Request,
        );

        expect($result->replayed)->toBeFalse()
            ->and($result->rewardsIssued)->toBe([])
            ->and($result->transaction->type)->toBe(TransactionType::Stamp)
            ->and($result->transaction->quantity)->toBe(1)
            ->and($result->transaction->staff_user_id)->toBe($this->staff->id)
            ->and($result->transaction->location_id)->toBe($this->location->id)
            ->and($result->transaction->source)->toBe(TransactionSource::Request)
            ->and($result->transaction->idempotency_key)->toBe('key-1')
            ->and($result->transaction->business_id)->toBe($this->business->id)
            ->and($result->transaction->meta)->toMatchArray(['balance_before' => 0, 'balance_after' => 1])
            ->and($result->card->stamps_balance)->toBe(1);
    });

    it('issues a reward on the 5th stamp and resets the balance', function () {
        foreach (range(1, 4) as $ignored) {
            ($this->stamp)();
        }

        $result = ($this->stamp)();

        expect($result->rewardsIssued)->toHaveCount(1)
            ->and($result->card->stamps_balance)->toBe(0)
            ->and($result->card->rewards_available)->toBe(1);

        $reward = Reward::where('card_id', $this->card->id)->sole();
        expect($reward->status)->toBe(RewardStatus::Available)
            ->and($reward->issued_by_transaction_id)->toBe($result->transaction->id)
            ->and($reward->expires_at)->toBeNull();
    });

    it('drops the surplus without rollover and keeps it with rollover', function (bool $rollover, int $expectedBalance) {
        $this->program->update(['rollover_extra' => $rollover, 'max_stamps_per_visit' => 3]);

        ($this->stamp)(3);
        $result = ($this->stamp)(3); // 6 din 5

        expect($result->card->stamps_balance)->toBe($expectedBalance)
            ->and($result->card->rewards_available)->toBe(1);
    })->with([
        'without rollover' => [false, 0],
        'with rollover' => [true, 1],
    ]);

    it('issues several rewards from one stamping when rollover allows it', function () {
        $this->program->update(['stamps_required' => 2, 'max_stamps_per_visit' => 5, 'rollover_extra' => true]);

        $result = ($this->stamp)(5);

        expect($result->rewardsIssued)->toHaveCount(2)
            ->and($result->card->stamps_balance)->toBe(1)
            ->and($result->card->rewards_available)->toBe(2);
    });

    it('sets the reward expiry from reward_expire_days', function () {
        Carbon::setTestNow('2026-10-15 10:00:00');
        $this->program->update(['stamps_required' => 1, 'reward_expire_days' => 30]);

        $result = ($this->stamp)();

        expect($result->rewardsIssued[0]->expires_at->toDateTimeString())->toBe('2026-11-14 10:00:00');
    });
});

describe('rules', function () {
    it('rejects a stamp during the cooldown with a clear message and allows it afterwards', function () {
        $this->program->update(['cooldown_minutes' => 15]);
        Carbon::setTestNow('2026-10-15 10:00:00');
        ($this->stamp)();

        Carbon::setTestNow('2026-10-15 10:02:00');
        expect(fn () => ($this->stamp)())
            ->toThrow(StampRuleViolation::class, 'Ultima ștampilă acum 2 min – așteaptă 13 min.');

        Carbon::setTestNow('2026-10-15 10:15:00');
        expect(($this->stamp)()->card->stamps_balance)->toBe(2);
    });

    it('rounds the remaining cooldown up to the next minute', function () {
        $this->program->update(['cooldown_minutes' => 30]);
        Carbon::setTestNow('2026-10-15 10:00:00');
        ($this->stamp)();

        Carbon::setTestNow('2026-10-15 10:29:30');
        expect(fn () => ($this->stamp)())
            ->toThrow(StampRuleViolation::class, 'Ultima ștampilă acum 29 min – așteaptă 1 min.');
    });

    it('enforces the daily limit in stamps', function () {
        $this->program->update(['daily_limit' => 2, 'max_stamps_per_visit' => 2]);
        Carbon::setTestNow('2026-10-15 09:00:00');
        ($this->stamp)();

        expect(fn () => ($this->stamp)(2))
            ->toThrow(StampRuleViolation::class, 'Limita zilnică de 2 ștampile a fost atinsă (azi: 1).');

        expect(($this->stamp)()->card->stamps_balance)->toBe(2);

        expect(fn () => ($this->stamp)())->toThrow(StampRuleViolation::class);
    });

    it('resets the daily limit at midnight in the business timezone', function () {
        // Europe/Bucharest e UTC+3 în octombrie (ora de vară).
        $this->program->update(['daily_limit' => 1]);

        Carbon::setTestNow('2026-10-15 20:30:00'); // 23:30 la București
        ($this->stamp)();

        Carbon::setTestNow('2026-10-15 20:50:00'); // 23:50 – aceeași zi locală
        expect(fn () => ($this->stamp)())->toThrow(StampRuleViolation::class);

        Carbon::setTestNow('2026-10-15 21:30:00'); // 00:30 – zi nouă la București, aceeași zi în UTC
        expect(($this->stamp)()->card->stamps_balance)->toBe(2);
    });

    it('counts the day in the timezone of the business', function () {
        $this->business->update(['timezone' => 'America/New_York']); // UTC-4 în octombrie
        $this->program->update(['daily_limit' => 1]);

        Carbon::setTestNow('2026-10-15 02:00:00'); // 22:00 pe 14 la New York
        ($this->stamp)();

        Carbon::setTestNow('2026-10-15 05:00:00'); // 01:00 pe 15 la New York
        expect(($this->stamp)()->card->stamps_balance)->toBe(2);
    });

    it('rejects quantities outside 1..max_stamps_per_visit', function (int $quantity, string $message) {
        $this->program->update(['max_stamps_per_visit' => 2]);

        expect(fn () => ($this->stamp)($quantity))->toThrow(StampRuleViolation::class, $message);
    })->with([
        'zero' => [0, 'Numărul de ștampile trebuie să fie cel puțin 1.'],
        'negative' => [-1, 'Numărul de ștampile trebuie să fie cel puțin 1.'],
        'over max' => [3, 'Maxim 2 ștampile pe vizită.'],
    ]);

    it('rejects stamps when the program is not active', function (ProgramStatus $status) {
        $this->program->update(['status' => $status]);

        expect(fn () => ($this->stamp)())
            ->toThrow(StampRuleViolation::class, 'Programul nu este activ.');
    })->with([ProgramStatus::Draft, ProgramStatus::Paused, ProgramStatus::Archived]);

    it('rejects stamps when the business is suspended', function () {
        $this->business->status = BusinessStatus::Suspended;
        $this->business->save();

        expect(fn () => ($this->stamp)())
            ->toThrow(StampRuleViolation::class, 'Afacerea este suspendată.');
    });

    it('writes nothing when a rule is violated', function () {
        $this->program->update(['cooldown_minutes' => 30]);
        ($this->stamp)();
        $before = [CardTransaction::count(), AuditLog::count(), $this->card->fresh()->stamps_balance];

        expect(fn () => ($this->stamp)())->toThrow(StampRuleViolation::class);

        expect([CardTransaction::count(), AuditLog::count(), $this->card->fresh()->stamps_balance])->toBe($before);
    });
});

describe('idempotency', function () {
    it('creates a single transaction for the same idempotency key', function () {
        $first = ($this->stamp)(1, 'same-key');
        $second = ($this->stamp)(1, 'same-key');

        expect($second->replayed)->toBeTrue()
            ->and($second->transaction->id)->toBe($first->transaction->id)
            ->and($second->card->stamps_balance)->toBe(1)
            ->and(CardTransaction::where('card_id', $this->card->id)->count())->toBe(1);
    });

    it('replays instead of reporting the cooldown', function () {
        $this->program->update(['cooldown_minutes' => 30]);
        ($this->stamp)(1, 'double-click');

        expect(($this->stamp)(1, 'double-click')->replayed)->toBeTrue();
    });

    it('replays the rewards issued by the original stamp', function () {
        $this->program->update(['stamps_required' => 1]);

        $first = ($this->stamp)(1, 'k');
        $second = ($this->stamp)(1, 'k');

        expect($second->rewardsIssued)->toHaveCount(1)
            ->and($second->rewardsIssued[0]->id)->toBe($first->rewardsIssued[0]->id)
            ->and(Reward::count())->toBe(1);
    });

    it('rejects the same key with a different quantity or location', function () {
        $this->program->update(['max_stamps_per_visit' => 3]);
        ($this->stamp)(1, 'k');

        expect(fn () => ($this->stamp)(3, 'k'))->toThrow(IdempotencyConflict::class)
            ->and(fn () => ($this->stamp)(1, 'k', location: $this->otherLocation))->toThrow(IdempotencyConflict::class);
    });

    it('rejects the same key for another card', function () {
        $otherCard = Card::factory()->for($this->program)->create();
        ($this->stamp)(1, 'k');

        expect(fn () => ($this->stamp)(1, 'k', $otherCard))->toThrow(IdempotencyConflict::class);
    });

    it('lets two businesses use the same key independently', function () {
        $other = loyaltyFixture();
        ($this->stamp)(1, 'shared');

        $result = ($this->stamp)(1, 'shared', $other['card'], $other['staff'], $other['location']);

        expect($result->replayed)->toBeFalse();
    });
});

describe('authorization and tenancy', function () {
    it('lets every role of the business stamp', function (string $member) {
        expect(($this->stamp)(staff: $this->{$member})->card->stamps_balance)->toBe(1);
    })->with(['staff', 'manager', 'owner']);

    it('refuses staff of another business and users without membership', function () {
        $foreignStaff = User::factory()->withRole(Business::factory()->create(), Role::Owner)->create();
        $stranger = User::factory()->create();

        expect(fn () => ($this->stamp)(staff: $foreignStaff))->toThrow(AuthorizationException::class)
            ->and(fn () => ($this->stamp)(staff: $stranger))->toThrow(AuthorizationException::class)
            ->and(CardTransaction::count())->toBe(0);
    });

    it('keeps members bound to a location at that location', function () {
        $bound = User::factory()->withRole($this->business, Role::Staff, $this->location)->create();

        expect(($this->stamp)(staff: $bound)->card->stamps_balance)->toBe(1)
            ->and(fn () => ($this->stamp)(staff: $bound, location: $this->otherLocation))
            ->toThrow(AuthorizationException::class, 'Poți lucra doar în locația ta.')
            ->and(($this->stamp)(staff: $this->owner, location: $this->otherLocation)->card->stamps_balance)->toBe(2);
    });

    it('refuses a location of another business', function () {
        $foreignLocation = Location::factory()->create();

        expect(fn () => ($this->stamp)(location: $foreignLocation))->toThrow(TenancyViolation::class)
            ->and(CardTransaction::count())->toBe(0);
    });

    it('writes an audit log entry', function () {
        $result = ($this->stamp)();

        $log = AuditLog::sole();
        expect($log->action)->toBe('card.stamped')
            ->and($log->business_id)->toBe($this->business->id)
            ->and($log->actor_type)->toBe((new User)->getMorphClass())
            ->and($log->actor_id)->toBe($this->staff->id)
            ->and($log->subject_id)->toBe($result->transaction->id)
            ->and($log->payload)->toMatchArray(['card_id' => $this->card->id, 'quantity' => 1, 'location_id' => $this->location->id]);
    });
});
