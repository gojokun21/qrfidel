<?php

use App\Enums\TransactionType;
use App\Models\AuditLog;
use App\Models\Card;
use App\Models\CardTransaction;
use App\Models\User;
use App\Services\Loyalty\RewardService;
use App\Services\Loyalty\StampService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

beforeEach(function () {
    foreach (loyaltyFixture(['stamps_required' => 5, 'max_stamps_per_visit' => 3]) as $key => $value) {
        $this->{$key} = $value;
    }

    Carbon::setTestNow('2026-10-15 10:00:00');
    $this->card->customer->update(['email' => 'ana.popescu@client.test']);

    $this->stamp = fn (int $quantity = 1, $location = null, ?Card $card = null) => app(StampService::class)->add(
        card: $card ?? $this->card,
        staff: $this->owner,
        location: $location ?? $this->location,
        quantity: $quantity,
        idempotencyKey: (string) Str::ulid(),
    )->transaction;

    $this->voidUrl = fn (CardTransaction $transaction) => route('dashboard.transactions.void', $transaction);

    $this->void = fn (CardTransaction $transaction, array $data = [], ?User $as = null) => $this->actingAs($as ?? $this->owner)
        ->from(route('dashboard.transactions.index'))
        ->post(($this->voidUrl)($transaction), [
            'idempotency_key' => (string) Str::uuid(),
            'reason' => 'Ștampilă dată din greșeală',
            ...$data,
        ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

describe('list', function () {
    it('shows the recent transactions of the business to owners and managers', function () {
        ($this->stamp)(2);
        $foreign = loyaltyFixture();
        CardTransaction::factory()->for($foreign['card'])->create(['location_id' => $foreign['location']->id]);

        $this->actingAs($this->owner)
            ->get(route('dashboard.transactions.index'))
            ->assertOk()
            ->assertSee('Tranzacții')
            ->assertSee('an***@client.test')
            ->assertDontSee('ana.popescu@client.test')
            ->assertSee('Ștampilă ×2')
            ->assertSee($this->location->name)
            ->assertDontSee($foreign['location']->name);
    });

    it('forbids staff', function () {
        $this->actingAs($this->staff)->get(route('dashboard.transactions.index'))->assertForbidden();
    });

    it('shows a location-bound manager only the transactions of their location', function () {
        ($this->stamp)(1, $this->location);
        $elsewhere = Card::factory()->for($this->program)->create();
        $elsewhere->customer->update(['email' => 'altundeva@client.test']);
        ($this->stamp)(1, $this->otherLocation, $elsewhere);

        $this->actingAs($this->manager)
            ->get(route('dashboard.transactions.index', ['location' => $this->otherLocation->id]))
            ->assertOk()
            ->assertSee('an***@client.test')
            ->assertDontSee('al***@client.test');
    });

    it('filters by location and type', function () {
        ($this->stamp)(1, $this->location);
        $elsewhere = Card::factory()->for($this->program)->create();
        $elsewhere->customer->update(['email' => 'altundeva@client.test']);
        ($this->stamp)(1, $this->otherLocation, $elsewhere);

        $this->actingAs($this->owner)
            ->get(route('dashboard.transactions.index', ['location' => $this->otherLocation->id]))
            ->assertSee('al***@client.test')
            ->assertDontSee('an***@client.test');

        $this->actingAs($this->owner)
            ->get(route('dashboard.transactions.index', ['type' => TransactionType::Redeem->value]))
            ->assertSee('Nicio tranzacție')
            ->assertDontSee('an***@client.test');
    });

    it('offers the void button only on voidable transactions', function () {
        $old = ($this->stamp)(1);
        Carbon::setTestNow(now()->addHour());
        $latest = ($this->stamp)(1);
        giveReward($this->card);
        $redeem = app(RewardService::class)->redeem($this->card, $this->owner, $this->location, (string) Str::ulid())->transaction;

        $response = $this->actingAs($this->owner)->get(route('dashboard.transactions.index'))->assertOk();

        $response->assertSee(($this->voidUrl)($latest))
            ->assertSee(($this->voidUrl)($redeem))
            ->assertDontSee(($this->voidUrl)($old)); // nu e ultima ștampilă

        Carbon::setTestNow(now()->addHours(25));

        $this->actingAs($this->owner)->get(route('dashboard.transactions.index'))
            ->assertDontSee(($this->voidUrl)($latest))
            ->assertDontSee(($this->voidUrl)($redeem));
    });

    it('does not offer voids outside the location of a location-bound manager', function () {
        $elsewhere = Card::factory()->for($this->program)->create();
        $foreignStamp = ($this->stamp)(1, $this->otherLocation, $elsewhere);

        $this->actingAs($this->owner)->get(route('dashboard.transactions.index'))->assertSee(($this->voidUrl)($foreignStamp));
        $this->actingAs($this->manager)->get(route('dashboard.transactions.index'))->assertDontSee(($this->voidUrl)($foreignStamp));
    });
});

describe('void', function () {
    it('voids the transaction with an inverse transaction and the reason', function () {
        ($this->stamp)(1);
        $mistake = ($this->stamp)(2);

        ($this->void)($mistake)
            ->assertRedirect(route('dashboard.transactions.index'))
            ->assertSessionHas('status', 'Tranzacția a fost anulată.');

        $void = CardTransaction::query()->where('type', TransactionType::Void)->sole();

        expect($mistake->fresh()->voided_by_id)->toBe($void->id)
            ->and($void->staff_user_id)->toBe($this->owner->id)
            ->and($void->meta)->toMatchArray(['voided_transaction_id' => $mistake->id, 'reason' => 'Ștampilă dată din greșeală'])
            ->and($this->card->fresh()->stamps_balance)->toBe(1)
            ->and(AuditLog::query()->where('action', 'transaction.voided')->exists())->toBeTrue();
    });

    it('replays a double submit with the same key', function () {
        $mistake = ($this->stamp)(1);
        $key = (string) Str::uuid();

        ($this->void)($mistake, ['idempotency_key' => $key])->assertSessionHasNoErrors();
        ($this->void)($mistake, ['idempotency_key' => $key])->assertSessionHasNoErrors();

        expect(CardTransaction::query()->where('type', TransactionType::Void)->count())->toBe(1);
    });

    it('shows the business rule when the void is refused', function () {
        $old = ($this->stamp)(1);
        ($this->stamp)(1);

        ($this->void)($old)
            ->assertRedirect(route('dashboard.transactions.index'))
            ->assertSessionHasErrors(['loyalty' => 'Doar ultima ștampilă a cardului poate fi anulată.']);
    });

    it('validates the key and the reason', function () {
        $stamp = ($this->stamp)(1);

        ($this->void)($stamp, ['idempotency_key' => 'nu-e-uuid', 'reason' => str_repeat('a', 201)])
            ->assertSessionHasErrors(['idempotency_key', 'reason']);
    });

    it('allows a void without a reason', function () {
        $stamp = ($this->stamp)(1);

        ($this->void)($stamp, ['reason' => null])->assertSessionHasNoErrors();

        expect(CardTransaction::query()->where('type', TransactionType::Void)->sole()->meta['reason'])->toBeNull();
    });

    it('forbids staff and refuses a manager outside their location', function () {
        $elsewhere = Card::factory()->for($this->program)->create();
        $foreignStamp = ($this->stamp)(1, $this->otherLocation, $elsewhere);

        ($this->void)($foreignStamp, as: $this->staff)->assertForbidden();
        ($this->void)($foreignStamp, as: $this->manager)->assertSessionHasErrors(['loyalty' => 'Poți anula doar tranzacțiile din locația ta.']);

        expect($foreignStamp->fresh()->voided_by_id)->toBeNull();
    });

    it('hides the transactions of other businesses', function () {
        $foreign = loyaltyFixture();
        $transaction = CardTransaction::factory()->for($foreign['card'])->create(['location_id' => $foreign['location']->id]);

        ($this->void)($transaction)->assertNotFound();
    });
});

it('lets managers void from the card screen', function () {
    $stamp = ($this->stamp)(1);

    $this->actingAs($this->manager)
        ->get(route('staff.cards.show', $this->card))
        ->assertOk()
        ->assertSee(($this->voidUrl)($stamp));

    $this->actingAs($this->staff)
        ->get(route('staff.cards.show', $this->card))
        ->assertOk()
        ->assertDontSee(($this->voidUrl)($stamp));
});
