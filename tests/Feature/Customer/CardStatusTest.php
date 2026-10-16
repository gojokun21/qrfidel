<?php

use App\Enums\StampRequestStatus;
use App\Models\Card;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Program;
use App\Models\StampRequest;

beforeEach(function () {
    $this->program = Program::factory()->active()->create(['stamps_required' => 5]);
    $this->location = Location::factory()->create(['business_id' => $this->program->business_id]);
    $this->customer = Customer::factory()->create();
    $this->card = Card::factory()->for($this->program)->for($this->customer)->create();
    $this->card->forceFill(['stamps_balance' => 3, 'rewards_available' => 1])->save();

    $this->status = fn (?Customer $as = null) => $this->actingAs($as ?? $this->customer, 'customer')
        ->getJson(route('customer.cards.status', $this->card));
});

it('sends guests away', function () {
    $this->getJson(route('customer.cards.status', $this->card))->assertUnauthorized();
});

it('returns the progress of the card, never cached', function () {
    $response = ($this->status)()
        ->assertOk()
        ->assertExactJson([
            'stamps' => 3,
            'rewards' => 1,
            'request' => null,
        ]);

    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

it('includes the pending request and when it expires', function () {
    $request = StampRequest::factory()->for($this->card)->create(['location_id' => $this->location->id]);

    ($this->status)()->assertJson([
        'request' => [
            'id' => $request->id,
            'status' => 'pending',
            'expiresAt' => $request->expires_at->toIso8601String(),
        ],
    ]);
});

it('reports the outcome of the last request', function (StampRequestStatus $status) {
    StampRequest::factory()->for($this->card)->create([
        'location_id' => $this->location->id,
        'status' => $status,
        'handled_at' => now(),
    ]);

    ($this->status)()->assertJsonPath('request.status', $status->value);
})->with([StampRequestStatus::Rejected, StampRequestStatus::Expired]);

it('reports a pending request past its deadline as expired', function () {
    StampRequest::factory()->for($this->card)->create(['location_id' => $this->location->id]);
    $this->travel(11)->minutes();

    ($this->status)()->assertJsonPath('request.status', 'expired');
});

it('forgets requests handled long ago', function () {
    StampRequest::factory()->for($this->card)->create([
        'location_id' => $this->location->id,
        'status' => StampRequestStatus::Rejected,
        'handled_at' => now(),
    ]);
    $this->travel(31)->minutes();

    ($this->status)()->assertJsonPath('request', null);
});

it('hides the card of another customer behind a 404', function () {
    ($this->status)(Customer::factory()->create())->assertNotFound();
});

it('shows the outcome on the card page', function () {
    StampRequest::factory()->for($this->card)->create([
        'location_id' => $this->location->id,
        'status' => StampRequestStatus::Rejected,
        'handled_at' => now(),
    ]);

    $this->actingAs($this->customer, 'customer')
        ->get(route('customer.cards.show', $this->card))
        ->assertSee('Cererea a fost respinsă');
});
