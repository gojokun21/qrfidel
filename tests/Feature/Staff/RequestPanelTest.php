<?php

use App\Enums\Role;
use App\Enums\StampRequestStatus;
use App\Enums\TransactionSource;
use App\Models\Business;
use App\Models\Card;
use App\Models\CardTransaction;
use App\Models\Location;
use App\Models\Program;
use App\Models\StampRequest;
use App\Models\User;
use Illuminate\Support\Str;

beforeEach(function () {
    foreach (loyaltyFixture(['name' => 'Cafele fidele', 'stamps_required' => 5, 'max_stamps_per_visit' => 2]) as $key => $value) {
        $this->{$key} = $value;
    }

    $this->card->customer->update(['name' => 'Ana Popescu']);
    $this->card->forceFill(['stamps_balance' => 2])->save();
    $this->pending = StampRequest::factory()->for($this->card)->create(['location_id' => $this->location->id]);

    $this->atLocation = fn (User $user, ?Location $location = null) => $this->actingAs($user)
        ->withSession(['staff_location_id' => ($location ?? $this->location)->id]);

    $this->approve = fn (array $data = [], ?User $as = null, ?StampRequest $request = null) => ($this->atLocation)($as ?? $this->staff)
        ->postJson(route('staff.requests.approve', $request ?? $this->pending), [
            'quantity' => 1,
            'idempotency_key' => (string) Str::uuid(),
            ...$data,
        ]);

    $this->reject = fn (?User $as = null, ?StampRequest $request = null) => ($this->atLocation)($as ?? $this->staff)
        ->postJson(route('staff.requests.reject', $request ?? $this->pending));

    $this->transactions = fn () => CardTransaction::query()->withoutGlobalScopes()->where('card_id', $this->card->id);
});

describe('the panel page', function () {
    it('sends guests to the staff login', function () {
        $this->get(route('staff.requests'))->assertRedirect(route('login'));
        $this->getJson(route('staff.requests.feed'))->assertUnauthorized();
    });

    it('opens for every role, with the poller and the location', function (string $member) {
        ($this->atLocation)($this->{$member})
            ->get(route('staff.requests'))
            ->assertOk()
            ->assertSee('Cereri de ștampile')
            ->assertSee('requestPoller', false)
            ->assertSee(route('staff.requests.feed'), false)
            ->assertSee($this->location->name);
    })->with(['staff', 'manager', 'owner']);

    it('asks to choose the location first when the member has none', function () {
        $this->actingAs($this->staff)
            ->get(route('staff.requests'))
            ->assertOk()
            ->assertSee('Alege locația în care lucrezi')
            ->assertDontSee('requestPoller', false);

        $this->actingAs($this->staff)->getJson(route('staff.requests.feed'))
            ->assertStatus(422)
            ->assertJson(['message' => 'Alege locația în care lucrezi.']);
    });

    it('renders the open requests on first load', function () {
        ($this->atLocation)($this->staff)
            ->get(route('staff.requests'))
            ->assertSee('Ana Popescu');
    });
});

describe('the feed', function () {
    it('lists the open requests of the current location, with masked customer and progress', function () {
        $email = $this->card->customer->email;

        $response = ($this->atLocation)($this->staff)
            ->getJson(route('staff.requests.feed'))
            ->assertOk()
            ->assertJsonCount(1, 'requests')
            ->assertJsonPath('requests.0.id', $this->pending->id)
            ->assertJsonPath('requests.0.customer', 'Ana Popescu')
            ->assertJsonPath('requests.0.email', mb_substr($email, 0, 2).'***@'.explode('@', $email)[1])
            ->assertJsonPath('requests.0.program', 'Cafele fidele')
            ->assertJsonPath('requests.0.stamps', 2)
            ->assertJsonPath('requests.0.required', 5)
            ->assertJsonPath('requests.0.maxStamps', 2)
            ->assertJsonPath('requests.0.approveUrl', route('staff.requests.approve', $this->pending))
            ->assertJsonPath('requests.0.rejectUrl', route('staff.requests.reject', $this->pending));

        expect($response->getContent())->not->toContain($email)
            ->and($response->getContent())->not->toContain($this->card->public_id)
            ->and($response->headers->get('Cache-Control'))->toContain('no-store');
    });

    it('leaves out other locations, handled and expired requests', function () {
        StampRequest::factory()->for(Card::factory()->for($this->program))->create(['location_id' => $this->otherLocation->id]);
        StampRequest::factory()->for(Card::factory()->for($this->program))->create(['location_id' => $this->location->id, 'status' => StampRequestStatus::Rejected]);
        StampRequest::factory()->for(Card::factory()->for($this->program))->create(['location_id' => $this->location->id, 'expires_at' => now()->subMinute()]);

        ($this->atLocation)($this->staff)
            ->getJson(route('staff.requests.feed'))
            ->assertJsonCount(1, 'requests')
            ->assertJsonPath('requests.0.id', $this->pending->id);
    });

    it('never shows the requests of another business', function () {
        $foreign = loyaltyFixture();
        StampRequest::factory()->for($foreign['card'])->create(['location_id' => $foreign['location']->id]);

        ($this->atLocation)($foreign['staff'], $foreign['location'])
            ->getJson(route('staff.requests.feed'))
            ->assertJsonCount(1, 'requests')
            ->assertJsonMissing(['id' => $this->pending->id]);
    });

    it('shows a member bound to a location only that location', function () {
        ($this->atLocation)($this->manager, $this->otherLocation)
            ->getJson(route('staff.requests.feed'))
            ->assertJsonPath('requests.0.id', $this->pending->id);
    });
});

describe('approving and rejecting', function () {
    it('approves: one stamp from the request, at the counter location', function () {
        ($this->approve)()
            ->assertOk()
            ->assertJson(['message' => 'Ștampilă adăugată: 3 din 5.']);

        $transaction = ($this->transactions)()->sole();
        expect($transaction->source)->toBe(TransactionSource::Request)
            ->and($transaction->location_id)->toBe($this->location->id)
            ->and($this->pending->refresh()->status)->toBe(StampRequestStatus::Approved);
    });

    it('announces the reward when the card fills up', function () {
        $this->card->forceFill(['stamps_balance' => 3])->save();

        ($this->approve)(['quantity' => 2])
            ->assertOk()
            ->assertJson(['message' => 'Card complet! Recompensă câștigată: '.$this->program->reward_title.'.']);
    });

    it('replays a double click with the same key', function () {
        $key = (string) Str::uuid();

        ($this->approve)(['idempotency_key' => $key])->assertOk();
        ($this->approve)(['idempotency_key' => $key])->assertOk();

        expect(($this->transactions)()->count())->toBe(1);
    });

    it('accepts the key from the Idempotency-Key header', function () {
        ($this->atLocation)($this->staff)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson(route('staff.requests.approve', $this->pending), ['quantity' => 1])
            ->assertOk();
    });

    it('requires a key and a quantity', function () {
        ($this->atLocation)($this->staff)
            ->postJson(route('staff.requests.approve', $this->pending), [])
            ->assertJsonValidationErrors(['idempotency_key', 'quantity']);
    });

    it('explains a rule refusal with 422 and keeps the request', function () {
        ($this->approve)(['quantity' => 3])
            ->assertStatus(422)
            ->assertJson(['message' => 'Maxim 2 ștampile pe vizită.']);

        expect($this->pending->refresh()->status)->toBe(StampRequestStatus::Pending);
    });

    it('refuses an expired request', function () {
        $this->travel(11)->minutes();

        ($this->approve)()
            ->assertStatus(422)
            ->assertJson(['message' => 'Cererea a expirat – clientul poate trimite alta.']);
    });

    it('rejects', function () {
        ($this->reject)()->assertOk()->assertJson(['message' => 'Cerere respinsă.']);

        expect($this->pending->refresh()->status)->toBe(StampRequestStatus::Rejected)
            ->and(($this->transactions)()->count())->toBe(0);
    });

    it('works with plain forms too (redirect to the panel)', function () {
        ($this->atLocation)($this->staff)
            ->post(route('staff.requests.reject', $this->pending))
            ->assertRedirect(route('staff.requests'))
            ->assertSessionHas('status', 'Cerere respinsă.');
    });

    it('answers 404 for the request of another business', function () {
        $foreign = loyaltyFixture();
        $theirs = StampRequest::factory()->for($foreign['card'])->create(['location_id' => $foreign['location']->id]);

        ($this->approve)(request: $theirs)->assertNotFound();
        ($this->reject)(request: $theirs)->assertNotFound();
    });

    it('refuses a member bound to another location (403)', function () {
        $elsewhere = User::factory()->withRole($this->business, Role::Staff, $this->otherLocation)->create();

        ($this->approve)(as: $elsewhere)->assertForbidden();
        ($this->reject)(as: $elsewhere)->assertForbidden();
    });
});

it('links the panel in the sidebar', function () {
    ($this->atLocation)($this->staff)
        ->get(route('staff.scan'))
        ->assertSee(route('staff.requests'), false)
        ->assertSee('Cereri');
});

it('links the counter page from the locations list', function () {
    $this->actingAs($this->owner)
        ->get(route('dashboard.locations.index'))
        ->assertSee(route('customer.counter', $this->location->counter_token), false);
});

it('does not leak programs of other businesses through the counter of this one', function () {
    $foreign = Program::factory()->for(Business::factory())->active()->create();

    $this->actingAs($this->card->customer, 'customer')
        ->post(route('customer.counter.request', [$this->location->counter_token, $foreign]))
        ->assertNotFound();
});
