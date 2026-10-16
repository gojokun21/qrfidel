<?php

use App\Enums\StampRequestStatus;
use App\Models\Business;
use App\Models\Card;
use App\Models\CardTransaction;
use App\Models\Customer;
use App\Models\Invitation;
use App\Models\Location;
use App\Models\StampRequest;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('allows one card per customer and program', function () {
    $card = Card::factory()->create();

    inSavepoint(fn () => Card::factory()->create([
        'program_id' => $card->program_id,
        'customer_id' => $card->customer_id,
    ]));
})->throws(QueryException::class);

it('lets the same customer join programs of different businesses', function () {
    $customer = Customer::factory()->create();

    Card::factory()->for($customer)->create();
    Card::factory()->for($customer)->create();

    expect($customer->cards()->count())->toBe(2);
});

it('rejects a reused idempotency key', function () {
    $card = Card::factory()->create();
    CardTransaction::factory()->for($card)->create(['idempotency_key' => 'abc']);

    inSavepoint(fn () => CardTransaction::factory()->for($card)->create(['idempotency_key' => 'abc']));
})->throws(QueryException::class);

it('scopes idempotency keys per business', function () {
    CardTransaction::factory()->create(['idempotency_key' => 'abc']);
    CardTransaction::factory()->create(['idempotency_key' => 'abc']);

    expect(CardTransaction::where('idempotency_key', 'abc')->count())->toBe(2);
});

it('allows several transactions without an idempotency key', function () {
    $card = Card::factory()->create();
    CardTransaction::factory()->for($card)->count(2)->create(['idempotency_key' => null]);

    expect(CardTransaction::count())->toBe(2);
});

it('allows a single pending stamp request per card', function () {
    $card = Card::factory()->create();
    StampRequest::factory()->for($card)->create();

    inSavepoint(fn () => StampRequest::factory()->for($card)->create());
})->throws(QueryException::class);

it('allows a new request once the previous one is no longer pending', function () {
    $card = Card::factory()->create();
    StampRequest::factory()->for($card)->create(['status' => StampRequestStatus::Rejected]);
    StampRequest::factory()->for($card)->create(['status' => StampRequestStatus::Expired]);
    StampRequest::factory()->for($card)->create();

    expect($card->stampRequests()->count())->toBe(3);
});

it('keeps customer emails unique regardless of case', function () {
    Customer::factory()->create(['email' => 'ana@example.com']);

    inSavepoint(fn () => DB::table('customers')->insert([
        'email' => 'ANA@Example.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]));
})->throws(QueryException::class);

it('keeps business slugs unique', function () {
    Business::factory()->create(['slug' => 'cafenea']);

    inSavepoint(fn () => Business::factory()->create(['slug' => 'cafenea']));
})->throws(QueryException::class);

it('keeps counter tokens unique', function () {
    $location = Location::factory()->create();

    inSavepoint(fn () => Location::factory()->create(['counter_token' => $location->counter_token]));
})->throws(QueryException::class);

it('rejects invalid program rules at database level', function (array $attributes) {
    $businessId = Business::factory()->create()->id;

    inSavepoint(fn () => DB::table('programs')->insert(array_merge([
        'business_id' => $businessId,
        'name' => 'Test',
        'type' => 'stamps',
        'stamps_required' => 5,
        'reward_title' => 'Cafea gratis',
        'status' => 'draft',
    ], $attributes)));
})->throws(QueryException::class)->with([
    'zero stamps required' => [['stamps_required' => 0]],
    'zero stamps per visit' => [['max_stamps_per_visit' => 0]],
    'negative cooldown' => [['cooldown_minutes' => -1]],
    'zero daily limit' => [['daily_limit' => 0]],
    'unknown status' => [['status' => 'deleted']],
    'unknown type' => [['type' => 'cashback']],
    'invalid color' => [['color' => 'red']],
    'color without hash' => [['color' => '7c2d12']],
    'unknown stamp icon' => [['stamp_icon' => 'rocket']],
]);

it('rejects negative card balances at database level', function (string $column) {
    $card = Card::factory()->create();

    inSavepoint(fn () => DB::table('cards')->where('id', $card->id)->update([$column => -1]));
})->throws(QueryException::class)->with(['stamps_balance', 'rewards_available']);

describe('card_transactions is append-only', function () {
    beforeEach(function () {
        $this->transaction = CardTransaction::factory()->create(['quantity' => 1]);
    });

    it('refuses model updates', function () {
        $this->transaction->update(['quantity' => 5]);
    })->throws(LogicException::class);

    it('refuses model deletes', function () {
        $this->transaction->delete();
    })->throws(LogicException::class);

    it('refuses updates through the query builder', function () {
        inSavepoint(fn () => DB::table('card_transactions')->where('id', $this->transaction->id)->update(['quantity' => 5]));
    })->throws(QueryException::class);

    it('refuses deletes through the query builder', function () {
        inSavepoint(fn () => DB::table('card_transactions')->where('id', $this->transaction->id)->delete());
    })->throws(QueryException::class);

    it('allows marking a transaction as voided exactly once', function () {
        $void = CardTransaction::factory()->for($this->transaction->card)->void()->create();
        $again = CardTransaction::factory()->for($this->transaction->card)->void()->create();

        $this->transaction->update(['voided_by_id' => $void->id]);

        expect($this->transaction->fresh()->voided_by_id)->toBe($void->id)
            ->and(fn () => $this->transaction->fresh()->update(['voided_by_id' => $again->id]))->toThrow(LogicException::class)
            ->and(fn () => inSavepoint(fn () => DB::table('card_transactions')
                ->where('id', $this->transaction->id)
                ->update(['voided_by_id' => $again->id])))->toThrow(QueryException::class);
    });

    it('refuses a voided_by_id that is not a void of the same card', function (string $target) {
        $targets = [
            'self' => fn () => $this->transaction,
            'stamp on same card' => fn () => CardTransaction::factory()->for($this->transaction->card)->create(),
            'void on another card' => fn () => CardTransaction::factory()->void()->create(),
        ];
        $targetId = $targets[$target]()->id;

        expect(fn () => $this->transaction->update(['voided_by_id' => $targetId]))->toThrow(LogicException::class)
            ->and(fn () => inSavepoint(fn () => DB::table('card_transactions')
                ->where('id', $this->transaction->id)
                ->update(['voided_by_id' => $targetId])))->toThrow(QueryException::class);
    })->with(['self', 'stamp on same card', 'void on another card']);

    it('refuses changing other columns together with voided_by_id', function () {
        $void = CardTransaction::factory()->for($this->transaction->card)->void()->create();

        inSavepoint(fn () => DB::table('card_transactions')
            ->where('id', $this->transaction->id)
            ->update(['voided_by_id' => $void->id, 'quantity' => 9]));
    })->throws(QueryException::class);
});

it('allows a single active invitation per business and email', function () {
    $invitation = Invitation::factory()->create(['email' => 'a@exemplu.test']);

    inSavepoint(fn () => Invitation::factory()->for($invitation->business)->create(['email' => 'A@exemplu.test']));
})->throws(QueryException::class);

it('allows a new invitation once the previous one is revoked or accepted', function () {
    $business = Business::factory()->create();
    Invitation::factory()->for($business)->revoked()->create(['email' => 'a@exemplu.test']);
    Invitation::factory()->for($business)->accepted()->create(['email' => 'a@exemplu.test']);
    Invitation::factory()->for($business)->create(['email' => 'a@exemplu.test']);
    Invitation::factory()->create(['email' => 'a@exemplu.test']);

    expect(Invitation::query()->count())->toBe(4);
});
