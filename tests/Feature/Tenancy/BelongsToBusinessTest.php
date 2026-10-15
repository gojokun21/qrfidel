<?php

use App\Exceptions\TenancyViolation;
use App\Models\AuditLog;
use App\Models\Card;
use App\Models\CardTransaction;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Program;
use App\Models\Reward;
use App\Models\StampRequest;
use App\Support\Tenancy\CurrentBusiness;

$models = [
    'location' => Location::class,
    'program' => Program::class,
    'card' => Card::class,
    'transaction' => CardTransaction::class,
    'reward' => Reward::class,
    'stampRequest' => StampRequest::class,
    'auditLog' => AuditLog::class,
];

beforeEach(function () {
    $this->x = businessGraph();
    $this->y = businessGraph();
});

it('shows only the current business rows', function (string $key, string $class) {
    inBusiness($this->x['business']);

    expect($class::pluck('id')->all())->toBe([$this->x[$key]->id])
        ->and($class::find($this->y[$key]->id))->toBeNull();

    inBusiness($this->y['business']);

    expect($class::pluck('id')->all())->toBe([$this->y[$key]->id]);
})->with(fn () => collect($models)->map(fn ($class, $key) => [$key, $class])->all());

it('does not filter without a business context', function (string $key, string $class) {
    inBusiness(null);

    expect($class::count())->toBe(2);
})->with(fn () => collect($models)->map(fn ($class, $key) => [$key, $class])->all());

it('can bypass the scope explicitly', function () {
    inBusiness($this->x['business']);

    expect(Card::withoutGlobalScopes()->count())->toBe(2);
});

it('keeps the context isolated inside runAs', function () {
    $context = app(CurrentBusiness::class);
    inBusiness($this->x['business']);

    $ids = $context->runAs($this->y['business'], fn () => Program::pluck('id')->all());

    expect($ids)->toBe([$this->y['program']->id])
        ->and($context->id())->toBe($this->x['business']->id);
});

it('fills business_id from the current business on create', function () {
    inBusiness($this->x['business']);

    $location = Location::create(['name' => 'Centru']);

    expect($location->business_id)->toBe($this->x['business']->id);
});

it('refuses to create a business row without any business', function () {
    inBusiness(null);

    Location::create(['name' => 'Fără afacere']);
})->throws(TenancyViolation::class);

it('refuses to create a row for another business than the current one', function () {
    inBusiness($this->x['business']);

    Location::factory()->for($this->y['business'])->create();
})->throws(TenancyViolation::class);

it('derives business_id from the parent', function () {
    inBusiness(null);

    $card = Card::create([
        'program_id' => $this->y['program']->id,
        'customer_id' => Customer::factory()->create()->id,
    ]);

    expect($card->business_id)->toBe($this->y['business']->id);
});

it('rejects a business_id that does not match the parent', function () {
    inBusiness(null);

    Card::create([
        'business_id' => $this->x['business']->id,
        'program_id' => $this->y['program']->id,
        'customer_id' => Customer::factory()->create()->id,
    ]);
})->throws(TenancyViolation::class);

it('rejects a card of business Y created while acting in business X', function () {
    inBusiness($this->x['business']);

    Card::create([
        'program_id' => $this->y['program']->id,
        'customer_id' => Customer::factory()->create()->id,
    ]);
})->throws(TenancyViolation::class);

it('rejects a location from another business on a stamp request', function () {
    inBusiness(null);

    StampRequest::factory()->for($this->x['card'])->create(['location_id' => $this->y['location']->id]);
})->throws(TenancyViolation::class);

it('rejects a location from another business on a transaction', function () {
    inBusiness(null);

    CardTransaction::factory()->for($this->x['card'])->create(['location_id' => $this->y['location']->id]);
})->throws(TenancyViolation::class);

it('does not allow moving a row to another business', function () {
    inBusiness(null);

    $this->x['location']->update(['business_id' => $this->y['business']->id]);
})->throws(TenancyViolation::class);

it('allows platform-wide audit entries without a business', function () {
    inBusiness(null);

    $log = AuditLog::create(['action' => 'business.suspended']);

    expect($log->business_id)->toBeNull();
});

it('does not allow re-pointing a row to a parent from another business', function (string $key, string $foreignKey, string $parentKey) {
    inBusiness(null);

    $this->x[$key]->update([$foreignKey => $this->y[$parentKey]->id]);
})->throws(TenancyViolation::class)->with([
    'card → program' => ['card', 'program_id', 'program'],
    'stamp request → location' => ['stampRequest', 'location_id', 'location'],
    'reward → card' => ['reward', 'card_id', 'card'],
]);

it('allows re-pointing a row to a parent of the same business', function () {
    inBusiness(null);
    $other = Location::factory()->for($this->x['business'])->create();

    $this->x['stampRequest']->update(['location_id' => $other->id]);

    expect($this->x['stampRequest']->fresh()->location_id)->toBe($other->id);
});
