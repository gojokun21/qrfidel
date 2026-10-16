<?php

use App\Enums\BusinessStatus;
use App\Enums\ProgramStatus;
use App\Exceptions\InvalidQrToken;
use App\Models\Customer;
use App\Services\Loyalty\QrTokenService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;

beforeEach(function () {
    foreach (loyaltyFixture() as $key => $value) {
        $this->{$key} = $value;
    }

    $this->customer = $this->card->customer;
    $this->requestQr = fn (?Customer $as = null) => $this->actingAs($as ?? $this->customer, 'customer')
        ->postJson(route('customer.cards.qr', $this->card));
});

it('issues a fresh QR as JSON that is never cached', function () {
    Carbon::setTestNow('2026-10-16 12:00:00');

    $response = ($this->requestQr)()
        ->assertOk()
        ->assertExactJsonStructure(['token', 'shortCode', 'issuedAt', 'expiresAt', 'refreshSeconds'])
        ->assertJson([
            'issuedAt' => now()->toIso8601String(),
            'expiresAt' => now()->addSeconds(90)->toIso8601String(),
            'refreshSeconds' => 30,
        ]);

    expect($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->getContent())->not->toContain($this->card->secret)
        ->and($response->json('token'))->toStartWith($this->card->public_id.'.')
        ->and($response->json('shortCode'))->toMatch('/^[2-9A-HJKMNP-Z]{6}$/');
});

it('issues a token that a staff member can scan', function () {
    $token = ($this->requestQr)()->json('token');

    expect(app(QrTokenService::class)->resolve($token, $this->staff, $this->location)->is($this->card))->toBeTrue();
});

it('issues a short code that a staff member can type', function () {
    $code = ($this->requestQr)()->json('shortCode');

    expect(app(QrTokenService::class)->resolve($code, $this->staff, $this->location)->is($this->card))->toBeTrue();
});

it('rotates: each request gives a new token and the previous short code dies after the grace period', function () {
    Carbon::setTestNow('2026-10-16 12:00:00');
    $first = ($this->requestQr)()->json();
    $second = ($this->requestQr)()->json();

    Carbon::setTestNow(now()->addSeconds(config('loyalty.qr.short_code_grace_seconds') + 1));

    expect($second['token'])->not->toBe($first['token'])
        ->and(fn () => app(QrTokenService::class)->resolve($first['shortCode'], $this->staff, $this->location))
        ->toThrow(InvalidQrToken::class);
});

it('does not tell whether a card of another customer exists, even through the service', function () {
    try {
        app(QrTokenService::class)->issueForCustomer($this->card, Customer::factory()->create());
        $this->fail('Expected the card of another customer to be refused.');
    } catch (AuthorizationException $e) {
        expect($e->status())->toBe(404);
    }
});

it('hides the card of another customer behind a 404', function () {
    ($this->requestQr)(Customer::factory()->create())->assertNotFound();
});

it('requires a signed-in customer', function () {
    $this->postJson(route('customer.cards.qr', $this->card))->assertUnauthorized();

    $this->actingAs($this->staff)->postJson(route('customer.cards.qr', $this->card))->assertUnauthorized();
});

it('refuses a program that is not active with a clear message', function (ProgramStatus $status) {
    $this->program->update(['status' => $status]);

    ($this->requestQr)()->assertStatus(409)->assertJson(['message' => 'Programul nu este activ.']);
})->with([ProgramStatus::Paused, ProgramStatus::Archived]);

it('refuses a suspended business with a clear message', function () {
    $this->business->status = BusinessStatus::Suspended;
    $this->business->save();

    ($this->requestQr)()->assertStatus(409)->assertJson(['message' => 'Afacerea este suspendată.']);
});

it('limits a customer to 10 QR codes per minute', function () {
    foreach (range(1, 10) as $i) {
        ($this->requestQr)()->assertOk();
    }

    ($this->requestQr)()->assertTooManyRequests();

    // Limita e per client, nu per IP.
    $other = loyaltyFixture()['card'];
    $this->actingAs($other->customer, 'customer')->postJson(route('customer.cards.qr', $other))->assertOk();
});
