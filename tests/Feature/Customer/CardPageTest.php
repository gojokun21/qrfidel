<?php

use App\Enums\BusinessStatus;
use App\Enums\ProgramStatus;
use App\Models\Business;
use App\Models\Card;
use App\Models\Customer;
use App\Models\Program;

beforeEach(function () {
    $this->business = Business::factory()->create(['name' => 'Cafeneaua Centrală']);
    $this->program = Program::factory()->for($this->business)->active()->create([
        'name' => 'Cafele fidele',
        'stamps_required' => 5,
        'reward_title' => 'O cafea gratis',
    ]);
    $this->customer = Customer::factory()->create();
    $this->card = Card::factory()->for($this->program)->for($this->customer)->create();
    $this->card->forceFill(['stamps_balance' => 3])->save();
});

it('sends guests to the customer login', function () {
    $this->get(route('customer.cards.show', $this->card))->assertRedirect(route('customer.login'));
});

it('addresses the card by its public id, not the numeric one', function () {
    expect(route('customer.cards.show', $this->card))->toEndWith('/me/cards/'.$this->card->public_id);

    $this->actingAs($this->customer, 'customer')
        ->get('/me/cards/'.$this->card->id)
        ->assertNotFound();
});

it('shows the card with its progress and the rotating QR', function () {
    $this->actingAs($this->customer, 'customer')
        ->get(route('customer.cards.show', $this->card))
        ->assertOk()
        ->assertSee('Cafeneaua Centrală')
        ->assertSee('Cafele fidele')
        ->assertSee('O cafea gratis')
        ->assertSee(': 3 din 5 ștampile')
        ->assertSee('qrDisplay', false)
        ->assertSee(route('customer.cards.qr', $this->card), false)
        ->assertDontSee($this->card->secret);
});

it('shows the available rewards', function () {
    $this->card->forceFill(['rewards_available' => 2])->save();

    $this->actingAs($this->customer, 'customer')
        ->get(route('customer.cards.show', $this->card))
        ->assertSee('2 recompense disponibile');
});

it('hides the card of another customer behind a 404', function () {
    $this->actingAs(Customer::factory()->create(), 'customer')
        ->get(route('customer.cards.show', $this->card))
        ->assertNotFound();
});

it('shows no QR when the program is not active', function (ProgramStatus $status) {
    $this->program->update(['status' => $status]);

    $this->actingAs($this->customer, 'customer')
        ->get(route('customer.cards.show', $this->card))
        ->assertOk()
        ->assertSee('Program oprit')
        ->assertDontSee('qrDisplay', false);
})->with([ProgramStatus::Paused, ProgramStatus::Archived]);

it('keeps the QR on a stopped program while the card has rewards to use', function () {
    giveReward($this->card);
    $this->program->update(['status' => ProgramStatus::Paused]);

    $this->actingAs($this->customer, 'customer')
        ->get(route('customer.cards.show', $this->card))
        ->assertOk()
        ->assertSee('Programul e oprit – recompensele rămân valabile.')
        ->assertSee('qrDisplay', false);
});

it('shows no QR when the business is suspended', function () {
    $this->business->status = BusinessStatus::Suspended;
    $this->business->save();

    $this->actingAs($this->customer, 'customer')
        ->get(route('customer.cards.show', $this->card))
        ->assertOk()
        ->assertSee('Program oprit')
        ->assertDontSee('qrDisplay', false);
});

it('links every card on /me to its page', function () {
    $other = Card::factory()->for($this->customer)->create();

    $this->actingAs($this->customer, 'customer')
        ->get(route('customer.cards'))
        ->assertOk()
        ->assertSee(route('customer.cards.show', $this->card), false)
        ->assertSee(route('customer.cards.show', $other), false);
});
