<?php

use App\Enums\ProgramStatus;
use App\Enums\Role;
use App\Models\Card;
use App\Models\CardTransaction;
use App\Models\Customer;
use App\Models\User;
use App\Services\Loyalty\ScanTicketService;
use Illuminate\Support\Str;

beforeEach(function () {
    foreach (loyaltyFixture(['stamps_required' => 5, 'max_stamps_per_visit' => 2, 'reward_title' => 'O cafea gratis']) as $key => $value) {
        $this->{$key} = $value;
    }

    $this->card->customer->update(['name' => 'Ana Popescu']);
    $this->card->forceFill(['stamps_balance' => 2])->save();
    $this->ticket = app(ScanTicketService::class)->issue($this->card, $this->staff, $this->location);

    $this->show = fn (?string $ticket = null, ?User $as = null) => $this->actingAs($as ?? $this->staff)
        ->get(route('staff.cards.show', ['card' => $this->card, 'ticket' => $ticket ?? $this->ticket]));

    $this->stamp = fn (array $data = [], ?User $as = null) => $this->actingAs($as ?? $this->staff)
        ->post(route('staff.cards.stamp', $this->card), [
            'ticket' => $this->ticket,
            'quantity' => 1,
            'idempotency_key' => (string) Str::uuid(),
            ...$data,
        ]);

    $this->redeem = fn (array $data = [], ?User $as = null) => $this->actingAs($as ?? $this->staff)
        ->post(route('staff.cards.redeem', $this->card), [
            'ticket' => $this->ticket,
            'idempotency_key' => (string) Str::uuid(),
            ...$data,
        ]);

    $this->transactions = fn () => CardTransaction::query()->withoutGlobalScopes()->where('card_id', $this->card->id);
});

describe('card screen', function () {
    it('shows the customer, the program, the progress and the actions after a scan', function () {
        ($this->show)()
            ->assertOk()
            ->assertSee('Ana Popescu')
            ->assertSee(substr($this->card->customer->email, 0, 2).'***@')
            ->assertDontSee($this->card->customer->email)
            ->assertSee($this->program->name)
            ->assertSee('Card de fidelitate: 2 din 5 ștampile')
            ->assertSee('+1 ștampilă')
            ->assertSee($this->location->name)
            ->assertSee('name="idempotency_key"', false)
            ->assertDontSee($this->card->secret);
    });

    it('offers up to max_stamps_per_visit stamps', function () {
        ($this->show)()->assertSee('+2 ștampile')->assertDontSee('+3 ștampile');
    });

    it('shows the card but no actions without a valid scan', function () {
        ($this->show)('expired-or-forged')
            ->assertOk()
            ->assertSee('Card de fidelitate: 2 din 5 ștampile')
            ->assertSee('Scanează din nou')
            ->assertDontSee('+1 ștampilă');

        $this->actingAs($this->staff)->get(route('staff.cards.show', $this->card))->assertOk()->assertDontSee('+1 ștampilă');
    });

    it('does not show the actions of a ticket issued to a colleague', function () {
        $colleague = User::factory()->withRole($this->business, Role::Staff)->create();

        ($this->show)(null, $colleague)->assertOk()->assertDontSee('+1 ștampilă');
    });

    it('ignores a malformed ticket parameter', function () {
        $this->actingAs($this->staff)
            ->get(route('staff.cards.show', $this->card).'?ticket[]=x')
            ->assertOk()
            ->assertSee('Scanează din nou');
    });

    it('does not offer a reward that has expired, even if the cached counter still counts it', function () {
        giveReward($this->card, now()->subDay());

        ($this->show)()->assertDontSee('Folosește recompensa')->assertDontSee('recompensă disponibilă');
    });

    it('hides the card of another business behind a 404', function () {
        $foreign = Card::factory()->create();

        $this->actingAs($this->staff)->get(route('staff.cards.show', $foreign))->assertNotFound();
        $this->actingAs($this->staff)->post(route('staff.cards.stamp', $foreign), ['ticket' => 'x', 'quantity' => 1, 'idempotency_key' => (string) Str::uuid()])->assertNotFound();
    });

    it('lists the recent transactions', function () {
        ($this->stamp)();

        ($this->show)()->assertSee('Ștampilă')->assertSee($this->staff->name);
    });

    it('offers only the reward on a stopped program', function () {
        giveReward($this->card);
        $this->program->update(['status' => ProgramStatus::Paused]);

        ($this->show)()
            ->assertSee('Folosește recompensa')
            ->assertSee('Programul nu este activ')
            ->assertDontSee('+1 ștampilă');
    });
});

describe('stamp', function () {
    it('adds a stamp and goes back to the card with a confirmation', function () {
        ($this->stamp)()
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('staff.cards.show', ['card' => $this->card, 'ticket' => $this->ticket]))
            ->assertSessionHas('status', 'Ștampilă adăugată: 3 din 5.');

        expect($this->card->refresh()->stamps_balance)->toBe(3);
    });

    it('records one transaction for a double submit of the same form', function () {
        $key = (string) Str::uuid();

        ($this->stamp)(['idempotency_key' => $key])->assertSessionHasNoErrors();
        ($this->stamp)(['idempotency_key' => $key])->assertSessionHasNoErrors();

        expect(($this->transactions)()->count())->toBe(1)
            ->and($this->card->refresh()->stamps_balance)->toBe(3);
    });

    it('accepts the Idempotency-Key header', function () {
        $key = (string) Str::uuid();
        $data = ['ticket' => $this->ticket, 'quantity' => 1];

        $this->actingAs($this->staff)->withHeader('Idempotency-Key', $key)->postJson(route('staff.cards.stamp', $this->card), $data)->assertOk();
        $this->actingAs($this->staff)->withHeader('Idempotency-Key', $key)->postJson(route('staff.cards.stamp', $this->card), $data)->assertOk();

        expect(($this->transactions)()->value('idempotency_key'))->toBe($key)
            ->and(($this->transactions)()->count())->toBe(1);
    });

    it('requires a new scan for a second stamp', function () {
        ($this->stamp)()->assertSessionHasNoErrors();
        ($this->stamp)()->assertSessionHasErrors(['loyalty' => 'Această scanare a fost deja folosită – scanează din nou codul clientului.']);

        expect($this->card->refresh()->stamps_balance)->toBe(3);
    });

    it('refuses to stamp without a valid scan', function () {
        ($this->stamp)(['ticket' => 'forged'])->assertSessionHasErrors(['loyalty' => 'Scanează din nou codul clientului.']);

        expect(($this->transactions)()->count())->toBe(0);
    });

    it('validates the quantity and the idempotency key', function () {
        ($this->stamp)(['quantity' => 0])->assertSessionHasErrors('quantity');
        ($this->stamp)(['idempotency_key' => ''])->assertSessionHasErrors('idempotency_key');
        ($this->stamp)(['idempotency_key' => 'not-a-uuid'])->assertSessionHasErrors('idempotency_key');
    });

    it('explains the rule that refused the stamp and lets the staff member retry on the same scan', function () {
        ($this->stamp)(['quantity' => 3])->assertSessionHasErrors(['loyalty' => 'Maxim 2 ștampile pe vizită.']);
        ($this->stamp)(['quantity' => 2])->assertSessionHasNoErrors();

        expect($this->card->refresh()->stamps_balance)->toBe(4);
    });

    it('shows the cooldown message', function () {
        $this->program->update(['cooldown_minutes' => 30]);
        $this->card->forceFill(['last_stamp_at' => now()->subMinutes(2)])->save();

        ($this->stamp)()->assertSessionHasErrors(['loyalty' => 'Ultima ștampilă acum 2 min – așteaptă 28 min.']);
    });

    it('announces the reward on the stamp that completes the card', function () {
        ($this->stamp)(['quantity' => 2])->assertSessionHasNoErrors();
        $this->ticket = app(ScanTicketService::class)->issue($this->card, $this->staff, $this->location);

        ($this->stamp)()->assertSessionHas('status', 'Card complet! Recompensă câștigată: O cafea gratis.');

        expect($this->card->refresh()->rewards_available)->toBe(1);
    });

    it('forbids a member bound to another location from using a scan made here', function () {
        $bound = User::factory()->withRole($this->business, Role::Staff, $this->otherLocation)->create();
        $this->ticket = app(ScanTicketService::class)->issue($this->card, $bound, $this->location);

        ($this->stamp)([], $bound)->assertForbidden();
    });

    it('refuses a customer', function () {
        $this->actingAs(Customer::factory()->create(), 'customer')
            ->post(route('staff.cards.stamp', $this->card), ['ticket' => $this->ticket, 'quantity' => 1])
            ->assertRedirect(route('login'));
    });
});

describe('redeem', function () {
    it('uses the reward on the same scan as the stamp that earned it', function () {
        $this->card->forceFill(['stamps_balance' => 4])->save();

        ($this->stamp)()->assertSessionHasNoErrors();
        ($this->redeem)()
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('staff.cards.show', ['card' => $this->card, 'ticket' => $this->ticket]))
            ->assertSessionHas('status', 'Recompensă folosită: O cafea gratis.');

        expect($this->card->refresh())
            ->rewards_available->toBe(0)
            ->stamps_balance->toBe(0);
    });

    it('records one redeem for a double submit', function () {
        $this->card->forceFill(['stamps_balance' => 4])->save();
        ($this->stamp)();
        $key = (string) Str::uuid();

        ($this->redeem)(['idempotency_key' => $key])->assertSessionHasNoErrors();
        ($this->redeem)(['idempotency_key' => $key])->assertSessionHasNoErrors();

        expect(($this->transactions)()->where('type', 'redeem')->count())->toBe(1);
    });

    it('explains that there is no reward to use', function () {
        ($this->redeem)()->assertSessionHasErrors(['loyalty' => 'Nicio recompensă disponibilă pe acest card.']);
    });

    it('uses a reward on a stopped program', function () {
        $this->card->forceFill(['rewards_available' => 0])->save();
        $this->card->forceFill(['stamps_balance' => 4])->save();
        ($this->stamp)();
        $this->program->update(['status' => ProgramStatus::Paused]);

        ($this->redeem)()->assertSessionHasNoErrors();

        expect($this->card->refresh()->rewards_available)->toBe(0);
    });
});
