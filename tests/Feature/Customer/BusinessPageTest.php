<?php

use App\Enums\ProgramStatus;
use App\Models\Business;
use App\Models\Card;
use App\Models\Customer;
use App\Models\Program;

beforeEach(function () {
    $this->business = Business::factory()->create(['name' => 'Cafeneaua Centrală', 'slug' => 'cafeneaua-centrala']);
    $this->program = Program::factory()->for($this->business)->active()->create([
        'name' => 'Cafele fidele',
        'stamps_required' => 5,
        'reward_title' => 'O cafea gratis',
        'terms' => 'Valabil doar pentru cafeaua la pahar.',
    ]);
});

it('shows the business and its active programs to guests', function () {
    $this->get('/b/cafeneaua-centrala')
        ->assertOk()
        ->assertSee('Cafeneaua Centrală')
        ->assertSee('Cafele fidele')
        ->assertSee('O cafea gratis')
        ->assertSee('Valabil doar pentru cafeaua la pahar.')
        ->assertSee(': 0 din 5 ștampile');
});

it('hides programs that are not active', function (ProgramStatus $status) {
    Program::factory()->for($this->business)->create(['name' => 'Program ascuns', 'status' => $status]);

    $this->get(route('customer.business', $this->business))
        ->assertOk()
        ->assertDontSee('Program ascuns');
})->with([ProgramStatus::Draft, ProgramStatus::Paused, ProgramStatus::Archived]);

it('does not show programs of other businesses', function () {
    Program::factory()->active()->create(['name' => 'Program străin']);

    $this->get(route('customer.business', $this->business))->assertDontSee('Program străin');
});

it('says so when the business has no active program', function () {
    $this->program->forceFill(['status' => ProgramStatus::Paused])->save();

    $this->get(route('customer.business', $this->business))
        ->assertOk()
        ->assertSee('nu are încă un program activ');
});

it('returns 404 for an unknown or suspended business', function () {
    $this->get('/b/nu-exista')->assertNotFound();

    $this->business->forceFill(['status' => 'suspended'])->save();
    $this->get('/b/cafeneaua-centrala')->assertNotFound();
});

it('asks guests for their email and remembers the business', function () {
    $this->get(route('customer.business', $this->business))
        ->assertSee('action="'.route('customer.magic.send').'"', false)
        ->assertSee('name="business" value="cafeneaua-centrala"', false)
        ->assertDontSee('action="'.route('customer.enroll', [$this->business, $this->program]).'"', false);
});

it('offers enrollment to a signed-in customer', function () {
    $customer = Customer::factory()->create();

    $this->actingAs($customer, 'customer')
        ->get(route('customer.business', $this->business))
        ->assertSee('action="'.route('customer.enroll', [$this->business, $this->program]).'"', false)
        ->assertSee('Înscrie-te')
        ->assertDontSee('action="'.route('customer.magic.send').'"', false);
});

it('shows the real progress when the customer is already enrolled', function () {
    $customer = Customer::factory()->create();
    $card = Card::factory()->for($this->program)->for($customer)->create();
    $card->forceFill(['stamps_balance' => 3])->save();

    $this->actingAs($customer, 'customer')
        ->get(route('customer.business', $this->business))
        ->assertSee(': 3 din 5 ștampile')
        ->assertSee('Ești înscris')
        ->assertDontSee('action="'.route('customer.enroll', [$this->business, $this->program]).'"', false);
});

it('shows the confirmation after a link was requested', function () {
    $this->withSession(['magic_link_sent' => 'ana@client.test'])
        ->get(route('customer.business', $this->business))
        ->assertSee('Verifică-ți emailul')
        ->assertSee('ana@client.test');
});
