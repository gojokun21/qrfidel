<?php

use App\Enums\BusinessStatus;
use App\Enums\ProgramStatus;
use App\Enums\StampRequestStatus;
use App\Mail\CustomerMagicLink;
use App\Models\Business;
use App\Models\Card;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Program;
use App\Models\StampRequest;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    $this->business = Business::factory()->create(['name' => 'Cafeneaua Centrală', 'slug' => 'cafeneaua-centrala']);
    $this->location = Location::factory()->for($this->business)->create(['name' => 'Centru']);
    $this->program = Program::factory()->for($this->business)->active()->create([
        'name' => 'Cafele fidele',
        'stamps_required' => 5,
        'cooldown_minutes' => 0,
        'daily_limit' => null,
    ]);
    $this->customer = Customer::factory()->create();

    $this->counter = fn (?Location $location = null) => route('customer.counter', ($location ?? $this->location)->counter_token);
    $this->ask = fn (?Program $program = null) => $this->actingAs($this->customer, 'customer')
        ->post(route('customer.counter.request', [$this->location->counter_token, $program ?? $this->program]));
    $this->requests = fn () => StampRequest::query()->withoutGlobalScopes();
});

describe('the counter page', function () {
    it('addresses the location by its counter token', function () {
        expect(($this->counter)())->toEndWith('/l/'.$this->location->counter_token);
    });

    it('shows the business, the location and the email form to guests', function () {
        $this->get(($this->counter)())
            ->assertOk()
            ->assertSee('Cafeneaua Centrală')
            ->assertSee('Centru')
            ->assertSee('Primește linkul de conectare')
            ->assertSee('name="location" value="'.$this->location->counter_token.'"', false)
            ->assertDontSee('Cere ștampilă');
    });

    it('offers to enroll and ask in one tap when the customer has no card', function () {
        $this->actingAs($this->customer, 'customer')
            ->get(($this->counter)())
            ->assertOk()
            ->assertSee('Cafele fidele')
            ->assertSee('Înscrie-te și cere ștampila');
    });

    it('offers to ask for a stamp on an existing card, with its progress', function () {
        Card::factory()->for($this->program)->for($this->customer)->create()->forceFill(['stamps_balance' => 3])->save();

        $this->actingAs($this->customer, 'customer')
            ->get(($this->counter)())
            ->assertSee('Cere ștampilă')
            ->assertSee('3 din 5');
    });

    it('lists only the active programs', function () {
        Program::factory()->for($this->business)->create(['name' => 'Ciorna ascunsă', 'status' => ProgramStatus::Draft]);
        Program::factory()->for($this->business)->active()->create(['name' => 'Alt program activ']);

        $this->actingAs($this->customer, 'customer')
            ->get(($this->counter)())
            ->assertSee('Alt program activ')
            ->assertDontSee('Ciorna ascunsă');
    });

    it('shows a pending request instead of the button', function () {
        $card = Card::factory()->for($this->program)->for($this->customer)->create();
        StampRequest::factory()->for($card)->create(['location_id' => $this->location->id]);

        $this->actingAs($this->customer, 'customer')
            ->get(($this->counter)())
            ->assertSee('Cerere trimisă')
            ->assertSee(route('customer.cards.show', $card), false);
    });

    it('lets the customer move a request waiting at another location to this counter', function () {
        $card = Card::factory()->for($this->program)->for($this->customer)->create();
        $other = Location::factory()->for($this->business)->create();
        StampRequest::factory()->for($card)->create(['location_id' => $other->id]);

        $this->actingAs($this->customer, 'customer')
            ->get(($this->counter)())
            ->assertSee('Cere ștampilă')
            ->assertDontSee('Cerere trimisă');
    });

    it('answers 404 for an unknown token or a suspended business', function () {
        $this->get('/l/'.str_repeat('x', 40))->assertNotFound();

        $this->business->forceFill(['status' => BusinessStatus::Suspended])->save();
        $this->get(($this->counter)())->assertNotFound();
    });

    it('stops working once the owner regenerates the token', function () {
        $old = ($this->counter)();
        $this->location->forceFill(['counter_token' => str_repeat('n', 40)])->save();

        $this->get($old)->assertNotFound();
    });
});

describe('asking for a stamp', function () {
    it('sends guests to the customer login', function () {
        $this->post(route('customer.counter.request', [$this->location->counter_token, $this->program]))
            ->assertRedirect(route('customer.login'));
    });

    it('creates the request (and the card) and shows the card page waiting', function () {
        $response = ($this->ask)();

        $card = Card::query()->withoutGlobalScopes()->where('customer_id', $this->customer->id)->sole();
        $response->assertRedirect(route('customer.cards.show', $card));

        $request = ($this->requests)()->sole();
        expect($request->card_id)->toBe($card->id)
            ->and($request->location_id)->toBe($this->location->id)
            ->and($request->status)->toBe(StampRequestStatus::Pending);

        $this->actingAs($this->customer, 'customer')
            ->get(route('customer.cards.show', $card))
            ->assertSee('Cerere trimisă')
            ->assertSee('cardPoller', false)
            ->assertSee(route('customer.cards.status', $card), false);
    });

    it('keeps a single pending request per card', function () {
        ($this->ask)();
        ($this->ask)();

        expect(($this->requests)()->count())->toBe(1);
    });

    it('refuses a program of another business (404)', function () {
        $foreign = Program::factory()->for(Business::factory())->active()->create();

        ($this->ask)($foreign)->assertNotFound();
        expect(($this->requests)()->count())->toBe(0);
    });

    it('refuses a paused program with a message', function () {
        $this->program->update(['status' => ProgramStatus::Paused]);

        $this->from(($this->counter)());
        ($this->ask)()->assertRedirect(($this->counter)())->assertSessionHasErrors('loyalty');
        expect(($this->requests)()->count())->toBe(0);
    });

    it('limits new requests to 3 per hour per card, with a message in Romanian', function () {
        foreach (range(1, 3) as $i) {
            ($this->ask)();
            ($this->requests)()->where('status', 'pending')->update(['status' => 'rejected']);
        }

        $this->from(($this->counter)());
        ($this->ask)()->assertSessionHasErrors(['loyalty' => 'Ai trimis prea multe cereri pentru acest card. Încearcă din nou în 60 min.']);

        RateLimiter::clear('stamp-request:'.Card::query()->withoutGlobalScopes()->sole()->id);
    });
});

describe('signing in from the counter', function () {
    beforeEach(fn () => Mail::fake());

    it('brings the customer back to the counter after the magic link', function () {
        $this->post(route('customer.magic.send'), ['email' => 'ana@client.test', 'location' => $this->location->counter_token])
            ->assertSessionHasNoErrors();

        $url = null;
        Mail::assertSent(CustomerMagicLink::class, function (CustomerMagicLink $mail) use (&$url) {
            $url = $mail->url;

            return true;
        });

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        expect($query['l'])->toBe($this->location->counter_token)
            ->and($query['b'])->toBe('cafeneaua-centrala');

        $this->post($url)->assertRedirect(($this->counter)());
    });

    it('falls back to the business page when the token was regenerated meanwhile', function () {
        $this->post(route('customer.magic.send'), ['email' => 'ana@client.test', 'location' => $this->location->counter_token]);

        $url = null;
        Mail::assertSent(CustomerMagicLink::class, function (CustomerMagicLink $mail) use (&$url) {
            $url = $mail->url;

            return true;
        });

        $this->location->forceFill(['counter_token' => str_repeat('n', 40)])->save();

        $this->post($url)->assertRedirect(route('customer.business', $this->business));
    });

    it('ignores an unknown counter token', function () {
        $this->post(route('customer.magic.send'), ['email' => 'ana@client.test', 'location' => 'nu-exista'])
            ->assertSessionHasNoErrors();

        Mail::assertSent(CustomerMagicLink::class, function (CustomerMagicLink $mail) {
            parse_str((string) parse_url($mail->url, PHP_URL_QUERY), $query);

            return ! array_key_exists('l', $query);
        });
    });
});
