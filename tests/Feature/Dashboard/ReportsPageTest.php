<?php

use App\Models\Card;
use App\Services\Loyalty\StampService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

beforeEach(function () {
    foreach (loyaltyFixture(['max_stamps_per_visit' => 5]) as $key => $value) {
        $this->{$key} = $value;
    }

    Carbon::setTestNow('2026-10-15 09:00:00');

    $this->stamp = fn (int $quantity, $location, ?Card $card = null) => app(StampService::class)->add(
        card: $card ?? $this->card,
        staff: $this->staff,
        location: $location,
        quantity: $quantity,
        idempotencyKey: (string) Str::ulid(),
    );
});

afterEach(function () {
    Carbon::setTestNow();
});

it('forbids staff', function () {
    $this->actingAs($this->staff)->get(route('dashboard.reports'))->assertForbidden();
});

it('shows the figures of the business to the owner', function () {
    ($this->stamp)(2, $this->location);
    ($this->stamp)(3, $this->otherLocation, Card::factory()->for($this->program)->create());

    $this->actingAs($this->owner)
        ->get(route('dashboard.reports'))
        ->assertOk()
        ->assertSee('Rapoarte')
        ->assertSee('Ultimele 30 de zile')
        ->assertSeeInOrder(['Clienți activi', '2'])
        ->assertSeeInOrder(['Ștampile azi', '5'])
        ->assertSeeInOrder(['Top locații', $this->otherLocation->name, '3', $this->location->name, '2'])
        ->assertSeeInOrder(['Activitate per angajat', $this->staff->name, '5'])
        ->assertDontSee('Doar locația');
});

it('shows a location-bound manager only their location', function () {
    ($this->stamp)(2, $this->location);
    ($this->stamp)(3, $this->otherLocation, Card::factory()->for($this->program)->create());

    $this->actingAs($this->manager)
        ->get(route('dashboard.reports'))
        ->assertOk()
        ->assertSee('Doar locația '.$this->location->name)
        ->assertSeeInOrder(['Ștampile azi', '2'])
        ->assertDontSee($this->otherLocation->name);
});

it('switches the period and ignores unknown periods', function () {
    $this->actingAs($this->owner)
        ->get(route('dashboard.reports', ['zile' => 7]))
        ->assertOk()
        ->assertSee('Ultimele 7 zile')
        ->assertSee('aria-current="page"', false);

    $this->actingAs($this->owner)
        ->get(route('dashboard.reports', ['zile' => 5]))
        ->assertOk()
        ->assertSee('Ultimele 30 de zile');
});

it('hides the other businesses', function () {
    $foreign = loyaltyFixture();

    $this->actingAs($this->owner)
        ->get(route('dashboard.reports'))
        ->assertOk()
        ->assertDontSee($foreign['location']->name);
});

it('shows the quick figures on the home page to managers and owners only', function () {
    ($this->stamp)(2, $this->location);

    $this->actingAs($this->owner)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSeeInOrder(['Ștampile azi', '2'])
        ->assertSee(route('dashboard.reports'));

    $this->actingAs($this->staff)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Ștampile azi')
        ->assertDontSee(route('dashboard.reports'));
});
