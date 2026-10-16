<?php

use App\Enums\RewardStatus;
use App\Enums\TransactionType;
use App\Models\Card;
use App\Models\CardTransaction;
use App\Models\Location;
use App\Models\Program;
use App\Models\Reward;
use App\Models\User;
use App\Services\Loyalty\ReportService;
use App\Services\Loyalty\RewardService;
use App\Services\Loyalty\StampService;
use App\Services\Loyalty\VoidService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

beforeEach(function () {
    foreach (loyaltyFixture(['stamps_required' => 5, 'max_stamps_per_visit' => 5]) as $key => $value) {
        $this->{$key} = $value;
    }

    $this->business->update(['timezone' => 'Europe/Bucharest']);
    // 12:00 la București (UTC+3 în octombrie).
    Carbon::setTestNow('2026-10-15 09:00:00');

    $this->stamp = fn (int $quantity = 1, ?Location $location = null, ?Card $card = null, ?User $staff = null) => app(StampService::class)->add(
        card: $card ?? $this->card,
        staff: $staff ?? $this->staff,
        location: $location ?? $this->location,
        quantity: $quantity,
        idempotencyKey: (string) Str::ulid(),
    )->transaction;

    // Rând scris direct, la o dată anume (serviciile scriu mereu „acum”).
    $this->stampAt = fn (string $at, int $quantity = 1, ?Location $location = null, ?Card $card = null) => CardTransaction::factory()
        ->for($card ?? $this->card)
        ->create([
            'quantity' => $quantity,
            'location_id' => ($location ?? $this->location)->id,
            'staff_user_id' => $this->staff->id,
            'created_at' => Carbon::parse($at),
        ]);

    $this->report = fn (int $days = 30, ?User $as = null) => app(ReportService::class)
        ->forMember($this->business, ($as ?? $this->owner)->membershipIn($this->business), $days);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('sums stamp quantities and counts redeems', function () {
    ($this->stamp)(3);
    ($this->stamp)(2);
    $this->card->refresh();
    app(RewardService::class)->redeem($this->card, $this->staff, $this->location, (string) Str::ulid());

    $report = ($this->report)();

    expect($report->stamps)->toBe(5)
        ->and($report->stampsToday)->toBe(5)
        ->and($report->stampsWeek)->toBe(5)
        ->and($report->rewardsIssued)->toBe(1)
        ->and($report->rewardsRedeemed)->toBe(1)
        ->and($report->activeCustomers)->toBe(1);
});

it('leaves out voided stamps and redeems, void and expire rows', function () {
    ($this->stamp)(1);
    $mistake = ($this->stamp)(2);
    app(VoidService::class)->void($mistake, $this->owner, (string) Str::uuid());

    giveReward($this->card);
    $redeem = app(RewardService::class)->redeem($this->card->refresh(), $this->staff, $this->location, (string) Str::ulid())->transaction;
    app(VoidService::class)->void($redeem, $this->owner, (string) Str::uuid());

    CardTransaction::factory()->for($this->card)->create(['type' => TransactionType::Expire, 'quantity' => -1]);

    $report = ($this->report)();

    expect($report->stamps)->toBe(1)
        ->and($report->stampsToday)->toBe(1)
        ->and($report->rewardsRedeemed)->toBe(0);
});

it('does not count voided rewards as issued', function () {
    Reward::factory()->for($this->card)->create(['status' => RewardStatus::Voided]);
    Reward::factory()->for($this->card)->create(['status' => RewardStatus::Redeemed, 'redeemed_at' => now()]);
    Reward::factory()->for($this->card)->create(['issued_at' => now()->subDays(40)]);

    expect(($this->report)()->rewardsIssued)->toBe(1);
});

it('cuts the days at midnight in the timezone of the business', function () {
    // 2026-10-14 21:30 UTC = 2026-10-15 00:30 la București: azi.
    ($this->stampAt)('2026-10-14 21:30:00');
    // 2026-10-14 20:30 UTC = 23:30 la București: ieri.
    ($this->stampAt)('2026-10-14 20:30:00', 2);

    $report = ($this->report)(7);

    expect($report->stampsToday)->toBe(1)
        ->and($report->stamps)->toBe(3);
});

it('includes today and the previous days in the window', function () {
    // Fereastra de 7 zile începe pe 2026-10-09 00:00 la București = 2026-10-08 21:00 UTC.
    ($this->stampAt)('2026-10-08 21:00:00');
    ($this->stampAt)('2026-10-08 20:59:59', 4);
    ($this->stampAt)('2026-09-20 10:00:00', 2);

    expect(($this->report)(7)->stamps)->toBe(1)
        ->and(($this->report)(7)->stampsWeek)->toBe(1)
        ->and(($this->report)(30)->stamps)->toBe(7)
        ->and(($this->report)(30)->stampsWeek)->toBe(1);
});

it('counts distinct active customers in the window', function () {
    $other = Card::factory()->for($this->program)->create();
    $sameCustomerOtherProgram = Card::factory()
        ->for(Program::factory()->for($this->business)->active()->create(['cooldown_minutes' => 0, 'daily_limit' => null]))
        ->create(['customer_id' => $this->card->customer_id]);
    $inactive = Card::factory()->for($this->program)->create();

    ($this->stamp)(1);
    ($this->stamp)(1, card: $sameCustomerOtherProgram);
    ($this->stamp)(1, card: $other);
    ($this->stampAt)('2026-08-01 10:00:00', card: $inactive);

    expect(($this->report)(30)->activeCustomers)->toBe(2)
        ->and(($this->report)(90)->activeCustomers)->toBe(3);
});

it('ranks the locations and lists the ones without activity', function () {
    ($this->stamp)(1);
    $other = Card::factory()->for($this->program)->create();
    ($this->stamp)(3, $this->otherLocation, $other);
    giveReward($other);
    app(RewardService::class)->redeem($other->refresh(), $this->staff, $this->otherLocation, (string) Str::ulid());
    $idle = Location::factory()->for($this->business)->create(['name' => 'Zzz Inactivă']);

    $locations = ($this->report)()->locations;

    expect($locations)->toHaveCount(3)
        ->and($locations[0])->toMatchArray(['id' => $this->otherLocation->id, 'stamps' => 3, 'redeems' => 1])
        ->and($locations[1])->toMatchArray(['id' => $this->location->id, 'stamps' => 1, 'redeems' => 0])
        ->and($locations[2])->toMatchArray(['id' => $idle->id, 'name' => 'Zzz Inactivă', 'stamps' => 0, 'redeems' => 0]);
});

it('shows the activity of each staff member, voids included', function () {
    ($this->stamp)(2);
    $mistake = ($this->stamp)(1, staff: $this->owner);
    app(VoidService::class)->void($mistake, $this->manager, (string) Str::uuid());
    giveReward($this->card);
    app(RewardService::class)->redeem($this->card->refresh(), $this->staff, $this->location, (string) Str::ulid());

    $staff = collect(($this->report)()->staff)->keyBy('id');

    expect($staff)->toHaveCount(2)
        ->and($staff[$this->staff->id])->toMatchArray(['name' => $this->staff->name, 'stamps' => 2, 'redeems' => 1, 'voids' => 0])
        ->and($staff[$this->manager->id])->toMatchArray(['stamps' => 0, 'redeems' => 0, 'voids' => 1])
        ->and(($this->report)()->staff[0]['id'])->toBe($this->staff->id);
});

it('limits a location-bound manager to their location', function () {
    $other = Card::factory()->for($this->program)->create();
    ($this->stamp)(1);
    ($this->stamp)(5, $this->otherLocation, $other); // emite o recompensă la cealaltă locație
    ($this->stamp)(5); // emite o recompensă aici

    $manager = ($this->report)(as: $this->manager);
    $owner = ($this->report)();

    expect($manager->locationId)->toBe($this->location->id)
        ->and($manager->stamps)->toBe(6)
        ->and($manager->activeCustomers)->toBe(1)
        ->and($manager->rewardsIssued)->toBe(1)
        ->and(collect($manager->locations)->pluck('id')->all())->toBe([$this->location->id])
        ->and($owner->locationId)->toBeNull()
        ->and($owner->stamps)->toBe(11)
        ->and($owner->rewardsIssued)->toBe(2);
});

it('lets a manager without a location see the whole business', function () {
    $this->manager->businesses()->updateExistingPivot($this->business->id, ['location_id' => null]);
    ($this->stamp)(1, $this->otherLocation);

    expect(($this->report)(as: $this->manager->fresh())->stamps)->toBe(1);
});

it('never mixes in other businesses', function () {
    $foreign = loyaltyFixture();
    CardTransaction::factory()->for($foreign['card'])->create(['location_id' => $foreign['location']->id, 'quantity' => 3]);
    Reward::factory()->for($foreign['card'])->create();

    $report = ($this->report)();

    expect($report->stamps)->toBe(0)
        ->and($report->rewardsIssued)->toBe(0)
        ->and($report->activeCustomers)->toBe(0)
        ->and(collect($report->locations)->pluck('id'))->not->toContain($foreign['location']->id);
});

it('gives the home page only the quick figures, with the same rules', function () {
    ($this->stamp)(2);
    ($this->stampAt)('2026-10-10 10:00:00', 3);

    expect(app(ReportService::class)->headline($this->business, $this->manager->membershipIn($this->business)))
        ->toBe(['stampsToday' => 2, 'stampsWeek' => 5, 'activeCustomers' => 1]);
});

it('refuses a membership of another business', function () {
    $foreign = loyaltyFixture();

    app(ReportService::class)->forMember($this->business, $foreign['owner']->membershipIn($foreign['business']));
})->throws(InvalidArgumentException::class);

it('falls back to the default period for unknown values', function () {
    expect(ReportService::period(7))->toBe(7)
        ->and(ReportService::period(90))->toBe(90)
        ->and(ReportService::period(5))->toBe(ReportService::DEFAULT_PERIOD)
        ->and(ReportService::period(null))->toBe(ReportService::DEFAULT_PERIOD);
});
