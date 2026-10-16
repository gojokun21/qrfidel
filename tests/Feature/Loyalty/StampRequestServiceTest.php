<?php

use App\Enums\BusinessStatus;
use App\Enums\ProgramStatus;
use App\Enums\Role;
use App\Enums\StampRequestStatus;
use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Exceptions\EnrollmentRefused;
use App\Exceptions\IdempotencyConflict;
use App\Exceptions\StampRequestUnavailable;
use App\Exceptions\StampRuleViolation;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Card;
use App\Models\CardTransaction;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Program;
use App\Models\StampRequest;
use App\Models\User;
use App\Services\Loyalty\StampRequestService;
use App\Services\Loyalty\StampService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    foreach (loyaltyFixture(['stamps_required' => 5, 'max_stamps_per_visit' => 2]) as $key => $value) {
        $this->{$key} = $value;
    }

    $this->customer = $this->card->customer;
    $this->requests = app(StampRequestService::class);
    $this->transactions = fn () => CardTransaction::query()->withoutGlobalScopes()->where('card_id', $this->card->id);

    // Clientul trimite cererea fără context de afacere (zona client); angajatul lucrează în contextul afacerii.
    $this->ask = fn (?Location $location = null, ?Program $program = null, ?Customer $customer = null) => $this->requests->request(
        $customer ?? $this->customer,
        $location ?? $this->location,
        $program ?? $this->program,
    );
});

afterEach(fn () => RateLimiter::clear('stamp-request:'.$this->card->id));

describe('the customer asks for a stamp', function () {
    it('creates a pending request at the counter location, valid for 10 minutes', function () {
        $this->freezeSecond();

        $request = ($this->ask)();

        expect($request->status)->toBe(StampRequestStatus::Pending)
            ->and($request->card_id)->toBe($this->card->id)
            ->and($request->location_id)->toBe($this->location->id)
            ->and($request->business_id)->toBe($this->business->id)
            ->and($request->quantity)->toBe(1)
            ->and($request->expires_at->equalTo(now()->addMinutes(10)))->toBeTrue()
            ->and($request->wasRecentlyCreated)->toBeTrue();

        $audit = AuditLog::query()->withoutGlobalScopes()->where('action', 'stamp_request.created')->sole();
        expect($audit->actor_type)->toBe($this->customer->getMorphClass())
            ->and($audit->actor_id)->toBe($this->customer->id)
            ->and($audit->payload)->toMatchArray(['card_id' => $this->card->id, 'location_id' => $this->location->id]);
    });

    it('enrolls a customer who has no card yet, then creates the request', function () {
        $newcomer = Customer::factory()->create();

        $request = ($this->ask)(customer: $newcomer);

        $card = Card::query()->withoutGlobalScopes()->where('customer_id', $newcomer->id)->sole();
        expect($request->card_id)->toBe($card->id)
            ->and($card->program_id)->toBe($this->program->id)
            ->and($card->stamps_balance)->toBe(0);
    });

    it('returns the same pending request when the customer taps again', function () {
        $first = ($this->ask)();
        $again = ($this->ask)();

        expect($again->id)->toBe($first->id)
            ->and($again->wasRecentlyCreated)->toBeFalse()
            ->and(StampRequest::query()->withoutGlobalScopes()->count())->toBe(1);
    });

    it('expires the old request and creates a new one after 10 minutes', function () {
        $first = ($this->ask)();

        $this->travel(11)->minutes();
        $second = ($this->ask)();

        expect($second->id)->not->toBe($first->id)
            ->and($first->refresh()->status)->toBe(StampRequestStatus::Expired)
            ->and($second->status)->toBe(StampRequestStatus::Pending);
    });

    it('moves a pending request to the location where the customer asks again', function () {
        $first = ($this->ask)();
        $again = ($this->ask)($this->otherLocation);

        expect($again->id)->toBe($first->id)
            ->and($again->location_id)->toBe($this->otherLocation->id);
    });

    it('allows 3 new requests per hour per card (SDD §6)', function () {
        foreach (range(1, 3) as $i) {
            $request = ($this->ask)();
            $request->forceFill(['status' => StampRequestStatus::Rejected])->save();
        }

        expect(fn () => ($this->ask)())->toThrow(StampRequestUnavailable::class, 'Ai trimis prea multe cereri');

        $this->travel(61)->minutes();
        expect(($this->ask)()->status)->toBe(StampRequestStatus::Pending);
    });

    it('does not count taps that return the pending request', function () {
        foreach (range(1, 5) as $i) {
            ($this->ask)();
        }

        expect(StampRequest::query()->withoutGlobalScopes()->count())->toBe(1);
    });

    it('refuses a paused program', function () {
        $this->program->update(['status' => ProgramStatus::Paused]);

        expect(fn () => ($this->ask)())->toThrow(EnrollmentRefused::class)
            ->and(StampRequest::query()->withoutGlobalScopes()->count())->toBe(0);
    });

    it('refuses a suspended business', function () {
        $this->business->forceFill(['status' => BusinessStatus::Suspended])->save();

        expect(fn () => ($this->ask)())->toThrow(EnrollmentRefused::class);
    });

    it('refuses a program of another business than the counter', function () {
        $foreign = Program::factory()->for(Business::factory())->active()->create();

        expect(fn () => ($this->ask)(program: $foreign))->toThrow(ModelNotFoundException::class)
            ->and(Card::query()->withoutGlobalScopes()->where('program_id', $foreign->id)->count())->toBe(0);
    });
});

describe('the staff member handles the request', function () {
    beforeEach(function () {
        $this->pending = ($this->ask)();
        inBusiness($this->business);
    });

    it('approves: one stamp through StampService, from the request, at the counter location', function () {
        $this->card->forceFill(['stamps_balance' => 2])->save();

        $result = $this->requests->approve($this->pending, $this->staff, 1, 'key-1');
        $this->pending->refresh();

        expect($result->card->stamps_balance)->toBe(3)
            ->and($result->transaction->source)->toBe(TransactionSource::Request)
            ->and($result->transaction->location_id)->toBe($this->location->id)
            ->and($result->transaction->staff_user_id)->toBe($this->staff->id)
            ->and($result->transaction->meta)->toMatchArray(['stamp_request_id' => $this->pending->id])
            ->and($this->pending->status)->toBe(StampRequestStatus::Approved)
            ->and($this->pending->handled_by)->toBe($this->staff->id)
            ->and($this->pending->handled_at)->not->toBeNull()
            ->and($this->pending->card_transaction_id)->toBe($result->transaction->id);

        expect(AuditLog::query()->where('action', 'stamp_request.approved')->count())->toBe(1);
    });

    it('issues the reward on the fifth stamp', function () {
        $this->card->forceFill(['stamps_balance' => 4])->save();

        $result = $this->requests->approve($this->pending, $this->staff, 1, 'key-1');

        expect($result->rewardsIssued)->toHaveCount(1)
            ->and($result->card->stamps_balance)->toBe(0)
            ->and($result->card->rewards_available)->toBe(1);
    });

    it('lets the staff member give up to max_stamps_per_visit stamps', function () {
        $result = $this->requests->approve($this->pending, $this->staff, 2, 'key-1');

        expect($result->card->stamps_balance)->toBe(2)
            ->and($this->pending->refresh()->quantity)->toBe(2);

        $next = ($this->ask)();
        expect(fn () => $this->requests->approve($next, $this->staff, 3, 'key-2'))
            ->toThrow(StampRuleViolation::class, 'Maxim 2 ștampile pe vizită.')
            ->and($next->refresh()->status)->toBe(StampRequestStatus::Pending);
    });

    it('replays a double click with the same key: one transaction', function () {
        $this->requests->approve($this->pending, $this->staff, 1, 'key-1');
        $again = $this->requests->approve($this->pending, $this->staff, 1, 'key-1');

        expect($again->replayed)->toBeTrue()
            ->and(($this->transactions)()->count())->toBe(1)
            ->and($this->card->refresh()->stamps_balance)->toBe(1);
    });

    it('refuses to approve an approved request with a new key', function () {
        $this->requests->approve($this->pending, $this->staff, 1, 'key-1');

        expect(fn () => $this->requests->approve($this->pending, $this->manager, 1, 'key-2'))
            ->toThrow(StampRequestUnavailable::class, 'Cererea a fost deja tratată.')
            ->and(($this->transactions)()->count())->toBe(1);
    });

    it('refuses an expired request and marks it expired (SDD §12)', function () {
        $this->travel(11)->minutes();

        expect(fn () => $this->requests->approve($this->pending, $this->staff, 1, 'key-1'))
            ->toThrow(StampRequestUnavailable::class, 'Cererea a expirat')
            ->and($this->pending->refresh()->status)->toBe(StampRequestStatus::Expired)
            ->and(($this->transactions)()->count())->toBe(0);
    });

    it('refuses a rejected request', function () {
        $this->requests->reject($this->pending, $this->staff);

        expect(fn () => $this->requests->approve($this->pending, $this->staff, 1, 'key-1'))
            ->toThrow(StampRequestUnavailable::class, 'Cererea a fost deja tratată.');
    });

    it('keeps the request pending when a rule refuses the stamp', function () {
        $this->program->update(['cooldown_minutes' => 30]);
        $this->card->forceFill(['last_stamp_at' => now()->subMinutes(5)])->save();

        expect(fn () => $this->requests->approve($this->pending, $this->staff, 1, 'key-1'))
            ->toThrow(StampRuleViolation::class, 'Ultima ștampilă acum 5 min – așteaptă 25 min.')
            ->and($this->pending->refresh()->status)->toBe(StampRequestStatus::Pending)
            ->and($this->pending->handled_by)->toBeNull();
    });

    it('enforces the daily limit on requests too', function () {
        $this->program->update(['daily_limit' => 1]);
        $this->requests->approve($this->pending, $this->staff, 1, 'key-1');

        $next = ($this->ask)();

        expect(fn () => $this->requests->approve($next, $this->staff, 1, 'key-2'))
            ->toThrow(StampRuleViolation::class, 'Limita zilnică');
    });

    it('refuses a key already used for another action', function () {
        CardTransaction::factory()->for($this->card)->create([
            'location_id' => $this->location->id,
            'type' => TransactionType::Redeem,
            'idempotency_key' => 'used-key',
        ]);

        expect(fn () => $this->requests->approve($this->pending, $this->staff, 1, 'used-key'))
            ->toThrow(IdempotencyConflict::class)
            ->and($this->pending->refresh()->status)->toBe(StampRequestStatus::Pending);
    });

    it('refuses a key that replays an earlier stamp of the same card instead of giving a new one', function () {
        app(StampService::class)->add($this->card, $this->staff, $this->location, 1, 'scan-key');

        expect(fn () => $this->requests->approve($this->pending, $this->staff, 1, 'scan-key'))
            ->toThrow(IdempotencyConflict::class)
            ->and($this->pending->refresh()->status)->toBe(StampRequestStatus::Pending)
            ->and($this->card->refresh()->stamps_balance)->toBe(1);
    });

    it('refuses a staff member bound to another location', function () {
        $elsewhere = User::factory()->withRole($this->business, Role::Staff, $this->otherLocation)->create();

        expect(fn () => $this->requests->approve($this->pending, $elsewhere, 1, 'key-1'))->toThrow(AuthorizationException::class)
            ->and(fn () => $this->requests->reject($this->pending, $elsewhere))->toThrow(AuthorizationException::class)
            ->and($this->pending->refresh()->status)->toBe(StampRequestStatus::Pending);
    });

    it('refuses a member of another business', function () {
        $stranger = User::factory()->withRole(Business::factory()->create(), Role::Owner)->create();

        expect(fn () => $this->requests->approve($this->pending, $stranger, 1, 'key-1'))->toThrow(AuthorizationException::class)
            ->and(fn () => $this->requests->reject($this->pending, $stranger))->toThrow(AuthorizationException::class);
    });

    it('rejects: no stamp, the request is closed and audited', function () {
        $this->requests->reject($this->pending, $this->staff);
        $this->pending->refresh();

        expect($this->pending->status)->toBe(StampRequestStatus::Rejected)
            ->and($this->pending->handled_by)->toBe($this->staff->id)
            ->and($this->pending->handled_at)->not->toBeNull()
            ->and(($this->transactions)()->count())->toBe(0)
            ->and(AuditLog::query()->where('action', 'stamp_request.rejected')->count())->toBe(1);
    });

    it('treats a second reject as a no-op', function () {
        $this->requests->reject($this->pending, $this->staff);
        $this->requests->reject($this->pending, $this->manager);

        expect($this->pending->refresh()->handled_by)->toBe($this->staff->id)
            ->and(AuditLog::query()->where('action', 'stamp_request.rejected')->count())->toBe(1);
    });

    it('refuses to reject an approved request', function () {
        $this->requests->approve($this->pending, $this->staff, 1, 'key-1');

        expect(fn () => $this->requests->reject($this->pending, $this->staff))
            ->toThrow(StampRequestUnavailable::class, 'Cererea a fost deja tratată.')
            ->and($this->pending->refresh()->status)->toBe(StampRequestStatus::Approved);
    });

    it('lets the customer ask again once the request is handled', function () {
        $this->requests->approve($this->pending, $this->staff, 1, 'key-1');
        inBusiness(null);

        $next = ($this->ask)();

        expect($next->id)->not->toBe($this->pending->id)
            ->and($next->status)->toBe(StampRequestStatus::Pending);
    });
});

describe('the panel list', function () {
    it('lists the open requests of a location, oldest first', function () {
        $older = ($this->ask)();
        $this->travel(1)->minutes();
        $otherCard = Card::factory()->for($this->program)->create();
        $newer = ($this->ask)(customer: $otherCard->customer);
        $elsewhere = Card::factory()->for($this->program)->create();
        ($this->ask)($this->otherLocation, customer: $elsewhere->customer);
        $handled = Card::factory()->for($this->program)->create();
        ($this->ask)(customer: $handled->customer)->forceFill(['status' => StampRequestStatus::Rejected])->save();

        inBusiness($this->business);

        expect($this->requests->openFor($this->location)->pluck('id')->all())->toBe([$older->id, $newer->id]);

        $this->travel(9)->minutes();
        expect($this->requests->openFor($this->location)->pluck('id')->all())->toBe([$newer->id]);
    });

    it('shows a pending request past its deadline as expired', function () {
        $request = ($this->ask)();

        expect($request->effectiveStatus())->toBe(StampRequestStatus::Pending);

        $this->travel(11)->minutes();
        expect($request->effectiveStatus())->toBe(StampRequestStatus::Expired);
    });
});
