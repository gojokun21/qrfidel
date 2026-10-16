<?php

use App\Models\Business;
use App\Models\User;
use App\Services\Loyalty\ReportService;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Carbon;

// Rezultatul verificabil al sesiunii 11 (SDD §11.4): cifrele corecte pe seed-ul demo.
beforeEach(function () {
    Carbon::setTestNow('2026-10-15 09:00:00');
    $this->seed(DemoSeeder::class);

    $this->cafe = Business::query()->where('slug', 'cafenea-demo')->sole();
    $this->carwash = Business::query()->where('slug', 'spalatorie-demo')->sole();
    $this->reportOf = function (string $email, int $days = 30) {
        $business = str_contains($email, 'cafenea') ? $this->cafe : $this->carwash;

        return app(ReportService::class)->forMember($business, User::query()->where('email', $email)->sole()->membershipIn($business), $days);
    };
});

afterEach(function () {
    Carbon::setTestNow();
});

it('reports the demo cafe to its owner', function () {
    $report = ($this->reportOf)('owner@cafenea.test');

    expect($report->activeCustomers)->toBe(2)
        ->and($report->stampsToday)->toBe(0)
        ->and($report->stampsWeek)->toBe(8)
        ->and($report->stamps)->toBe(8)
        ->and($report->rewardsIssued)->toBe(1)
        ->and($report->rewardsRedeemed)->toBe(0)
        ->and(collect($report->locations)->map(fn ($l) => [$l['name'], $l['stamps']])->all())->toBe([['Centru', 8], ['Gară', 0]])
        ->and(collect($report->staff)->map(fn ($s) => [$s['name'], $s['stamps']])->all())->toBe([['Barista Demo', 8]]);
});

it('reports the demo carwash to its owner', function () {
    $report = ($this->reportOf)('owner@spalatorie.test');

    expect($report->activeCustomers)->toBe(1)
        ->and($report->stamps)->toBe(2)
        ->and($report->rewardsIssued)->toBe(0)
        ->and(collect($report->staff)->pluck('name')->all())->toBe(['Operator Spălătorie']);
});

it('reports only Centru to the demo manager', function () {
    $report = ($this->reportOf)('manager@cafenea.test');

    expect($report->stamps)->toBe(8)
        ->and(collect($report->locations)->pluck('name')->all())->toBe(['Centru']);
});

it('counts in the 7-day window only the stamps since that day', function () {
    // Bogdan are ștampile acum 5…1 zile, Ana acum 3…1 zile: toate în ultimele 7 zile.
    expect(($this->reportOf)('owner@cafenea.test', 7)->stamps)->toBe(8);
});

it('shows the seed figures on the reports page', function () {
    $owner = User::query()->where('email', 'owner@cafenea.test')->sole();

    $this->actingAs($owner)
        ->get(route('dashboard.reports'))
        ->assertOk()
        ->assertSeeInOrder(['Clienți activi', '2'])
        ->assertSeeInOrder(['Top locații', 'Centru', '8', 'Gară', '0'])
        ->assertSeeInOrder(['Activitate per angajat', 'Barista Demo', '8']);
});
