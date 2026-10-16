<?php

use App\Models\AuditLog;
use App\Models\Card;
use App\Models\Customer;
use App\Models\Program;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\Process;

/*
 * Două procese PHP reale înscriu același client în același program în același moment (dublu-click, două taburi).
 */

it('creates a single card when the same customer enrolls twice at once', function () {
    $program = Program::factory()->active()->create();
    $customer = Customer::factory()->create();

    $startAt = sprintf('%.6F', microtime(true) + 3);
    $env = [
        'APP_ENV' => 'testing',
        'DB_CONNECTION' => 'pgsql',
        'DB_DATABASE' => config('database.connections.pgsql.database'),
        'DB_URL' => '',
        'CACHE_STORE' => 'array',
        'LOYALTY_QR_STORE' => 'array',
        'SESSION_DRIVER' => 'array',
        'QUEUE_CONNECTION' => 'sync',
    ];
    $worker = fn () => [PHP_BINARY, base_path('tests/Concurrency/enroll-worker.php'), (string) $customer->id, (string) $program->id, $startAt];

    $results = Process::pool(function (Pool $pool) use ($worker, $env) {
        $pool->env($env)->timeout(60)->command($worker());
        $pool->env($env)->timeout(60)->command($worker());
    })->start()->wait();

    $outcomes = $results->collect()->map(function ($result) {
        expect($result->successful())->toBeTrue($result->errorOutput().$result->output());

        return json_decode($result->output(), true, flags: JSON_THROW_ON_ERROR);
    });

    $card = Card::query()->sole();

    expect($outcomes->where('late', true))->toHaveCount(0, 'Un proces a pornit după barieră: înscrierile nu au fost simultane.')
        ->and($outcomes->pluck('card_id')->unique()->all())->toBe([$card->id])
        ->and($outcomes->where('created', true))->toHaveCount(1)
        ->and(AuditLog::query()->where('action', 'card.enrolled')->count())->toBe(1);
});
