<?php

use App\Enums\Role;
use App\Models\Business;
use App\Models\CardTransaction;
use App\Models\Customer;
use App\Models\User;
use App\Services\Loyalty\PlatformOverview;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-10-15 09:00:00');

    $this->cafe = loyaltyFixture();
    $this->cafe['business']->forceFill(['name' => 'Cafenea Alfa', 'slug' => 'cafenea-alfa'])->save();
    $this->carwash = loyaltyFixture();
    $this->carwash['business']->forceFill(['name' => 'Spălătorie Beta', 'slug' => 'spalatorie-beta', 'status' => 'suspended'])->save();

    CardTransaction::factory()->for($this->cafe['card'])->create(['quantity' => 3, 'location_id' => $this->cafe['location']->id]);
    CardTransaction::factory()->for($this->carwash['card'])->create(['quantity' => 2, 'created_at' => now()->subDays(40)]);

    $this->admin = User::factory()->platformAdmin()->create();
});

afterEach(function () {
    Carbon::setTestNow();
});

it('sums the whole platform', function () {
    Customer::factory()->create(); // fără card

    $totals = app(PlatformOverview::class)->totals();

    expect($totals)->toBe([
        'businesses_active' => 1,
        'businesses_suspended' => 1,
        'customers' => 3,
        'cards' => 2,
        'stamps_30_days' => 3,
    ]);
});

it('cuts the 30 days in the timezone of each business, like its reports', function () {
    $this->cafe['business']->forceFill(['timezone' => 'Europe/Bucharest'])->save();
    // Fereastra începe pe 2026-09-16 00:00 la București = 2026-09-15 21:00 UTC.
    CardTransaction::factory()->for($this->cafe['card'])->create(['quantity' => 4, 'created_at' => '2026-09-15 21:30:00']);
    CardTransaction::factory()->for($this->cafe['card'])->create(['quantity' => 5, 'created_at' => '2026-09-15 20:30:00']);

    $row = app(PlatformOverview::class)->businesses()->firstWhere('slug', 'cafenea-alfa');
    $report = app(PlatformOverview::class)->details($this->cafe['business'])['report'];

    expect((int) $row->stamps_30_days)->toBe(7)
        ->and($report->stamps)->toBe(7)
        ->and(app(PlatformOverview::class)->totals()['stamps_30_days'])->toBe(7);
});

it('lists every business with its counts and owners', function () {
    $owner = $this->cafe['owner'];

    $this->actingAs($this->admin)
        ->get(route('admin.index'))
        ->assertOk()
        ->assertSee('Cafenea Alfa')
        ->assertSee('Spălătorie Beta')
        ->assertSee($owner->email)
        ->assertSee('Suspendată');

    $row = app(PlatformOverview::class)->businesses()->firstWhere('slug', 'cafenea-alfa');

    expect($row->locations_count)->toBe(2)
        ->and($row->cards_count)->toBe(1)
        ->and((int) $row->stamps_30_days)->toBe(3);
});

it('searches by name or slug and filters by status', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.index', ['q' => 'beta']))
        ->assertSee('Spălătorie Beta')
        ->assertDontSee('Cafenea Alfa');

    $this->actingAs($this->admin)
        ->get(route('admin.index', ['q' => 'cafenea-al']))
        ->assertSee('Cafenea Alfa')
        ->assertDontSee('Spălătorie Beta');

    $this->actingAs($this->admin)
        ->get(route('admin.index', ['status' => 'suspended']))
        ->assertSee('Spălătorie Beta')
        ->assertDontSee('Cafenea Alfa');
});

it('shows the details and the reports of one business', function () {
    $other = Business::factory()->create(['name' => 'Altă afacere']);
    User::factory()->withRole($other, Role::Owner)->create();

    $this->actingAs($this->admin)
        ->get(route('admin.businesses.show', $this->cafe['business']))
        ->assertOk()
        ->assertSee('Cafenea Alfa')
        ->assertSee($this->cafe['owner']->email)
        ->assertSee($this->cafe['location']->name)
        ->assertSee($this->cafe['program']->name)
        ->assertSeeInOrder(['Ștampile', '3'])
        ->assertSee('Suspendă afacerea')
        ->assertDontSee('Altă afacere');

    $this->actingAs($this->admin)
        ->get(route('admin.businesses.show', $this->carwash['business']))
        ->assertOk()
        ->assertSee('Reactivează afacerea');
});
