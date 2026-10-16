<?php

use App\Enums\StampRequestStatus;
use App\Models\CardTransaction;
use App\Models\StampRequest;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\Process;

/*
 * Doi angajați apasă „Aprobă” pe aceeași cerere în același moment (două tablete la casă).
 */

afterEach(function () {
    $this->truncateTablesForAllConnections();
});

it('produces a single stamp when two staff members approve the same request at once', function () {
    $fixture = loyaltyFixture();
    $request = StampRequest::factory()->for($fixture['card'])->create(['location_id' => $fixture['location']->id]);

    $startAt = sprintf('%.6F', microtime(true) + 3);
    $env = [
        'APP_ENV' => 'testing',
        'DB_CONNECTION' => 'pgsql',
        'DB_DATABASE' => config('database.connections.pgsql.database'),
        'DB_URL' => '',
        'CACHE_STORE' => 'array',
        'SESSION_DRIVER' => 'array',
        'QUEUE_CONNECTION' => 'sync',
    ];
    $worker = fn (string $staffId, string $key) => [
        PHP_BINARY, base_path('tests/Concurrency/stamp-request-worker.php'), (string) $request->id, $staffId, $key, $startAt,
    ];

    $results = Process::pool(function (Pool $pool) use ($worker, $env, $fixture) {
        $pool->env($env)->timeout(60)->command($worker((string) $fixture['staff']->id, 'approve-a'));
        $pool->env($env)->timeout(60)->command($worker((string) $fixture['owner']->id, 'approve-b'));
    })->start()->wait();

    $outcomes = $results->collect()->map(function ($result) {
        expect($result->successful())->toBeTrue($result->errorOutput().$result->output());

        return json_decode($result->output(), true, flags: JSON_THROW_ON_ERROR);
    });

    expect($outcomes->where('late', true))->toHaveCount(0, 'Un proces a pornit după barieră: aprobările nu au fost simultane.')
        ->and($outcomes->where('status', 'ok'))->toHaveCount(1)
        ->and($outcomes->firstWhere('status', 'refused')['message'] ?? null)->toBe('Cererea a fost deja tratată.')
        ->and(CardTransaction::query()->where('card_id', $fixture['card']->id)->count())->toBe(1)
        ->and($fixture['card']->fresh()->stamps_balance)->toBe(1)
        ->and($request->fresh()->status)->toBe(StampRequestStatus::Approved);
});
