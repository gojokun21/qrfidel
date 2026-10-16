<?php

use App\Enums\StampRequestStatus;
use App\Jobs\ExpireRewards;
use App\Jobs\ExpireStampRequests;
use App\Jobs\ExpireStamps;
use App\Models\StampRequest;
use App\Services\Loyalty\ExpirationService;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Carbon;

it('delegates each job to the expiration service', function (string $job, string $method) {
    $service = Mockery::mock(ExpirationService::class);
    $service->shouldReceive($method)->once()->andReturn(0);
    app()->instance(ExpirationService::class, $service);

    dispatch_sync(new $job);
})->with([
    'stamps' => [ExpireStamps::class, 'expireStamps'],
    'rewards' => [ExpireRewards::class, 'expireRewards'],
    'stamp requests' => [ExpireStampRequests::class, 'expireStampRequests'],
]);

it('queues the jobs and keeps a single copy in the queue', function (string $job) {
    expect(new $job)->toBeInstanceOf(ShouldQueue::class)->toBeInstanceOf(ShouldBeUnique::class);
})->with([ExpireStamps::class, ExpireRewards::class, ExpireStampRequests::class]);

it('schedules the jobs', function (string $job, string $expression) {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event) => $event instanceof CallbackEvent && $event->description === $job);

    expect($events)->toHaveCount(1);

    $event = $events->first();

    expect($event->expression)->toBe($expression)
        ->and($event->onOneServer)->toBeTrue()
        ->and((new $job)->uniqueFor)->toBeLessThanOrEqual(300);
})->with([
    'stamp requests every 5 minutes' => [ExpireStampRequests::class, '*/5 * * * *'],
    'rewards hourly' => [ExpireRewards::class, '0 * * * *'],
    'stamps hourly' => [ExpireStamps::class, '0 * * * *'],
]);

it('expires stamp requests end to end through the job', function () {
    $fixture = loyaltyFixture();
    $request = StampRequest::factory()->for($fixture['card'])->create([
        'location_id' => $fixture['location']->id,
        'expires_at' => Carbon::now()->subMinute(),
    ]);

    dispatch_sync(new ExpireStampRequests);

    expect($request->fresh()->status)->toBe(StampRequestStatus::Expired);
});
