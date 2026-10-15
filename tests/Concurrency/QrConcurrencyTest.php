<?php

use App\Services\Loyalty\QrTokenService;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;

/*
 * Două procese PHP reale scanează același QR în același moment, cu jti-urile pe Redis-ul real.
 */

beforeEach(function () {
    config(['loyalty.qr.store' => 'redis']);

    foreach (loyaltyFixture() as $key => $value) {
        $this->{$key} = $value;
    }

    $this->qrToken = app(QrTokenService::class)->issue($this->card);
});

afterEach(function () {
    Cache::store('redis')->forget('qr:jti:'.explode('.', $this->qrToken->token)[2]);
    Cache::store('redis')->forget("qr:code:{$this->business->id}:{$this->qrToken->shortCode}");
    Cache::store('redis')->forget("qr:code-of:{$this->card->id}");
    $this->truncateTablesForAllConnections();
});

it('lets exactly one of two simultaneous scans of the same QR through', function () {
    $startAt = sprintf('%.6F', microtime(true) + 3);
    $env = [
        'APP_ENV' => 'testing',
        'DB_CONNECTION' => 'pgsql',
        'DB_DATABASE' => config('database.connections.pgsql.database'),
        'DB_URL' => '',
        'CACHE_STORE' => 'array',
        'LOYALTY_QR_STORE' => 'redis',
        'CACHE_PREFIX' => config('cache.prefix'),
        'SESSION_DRIVER' => 'array',
        'QUEUE_CONNECTION' => 'sync',
    ];
    $worker = fn () => [
        PHP_BINARY, base_path('tests/Concurrency/qr-worker.php'),
        $this->qrToken->token, (string) $this->staff->id, (string) $this->location->id, $startAt,
    ];

    $results = Process::pool(function (Pool $pool) use ($worker, $env) {
        $pool->env($env)->timeout(60)->command($worker());
        $pool->env($env)->timeout(60)->command($worker());
    })->start()->wait();

    $outcomes = $results->collect()->map(function ($result) {
        expect($result->successful())->toBeTrue($result->errorOutput().$result->output());

        return json_decode($result->output(), true, flags: JSON_THROW_ON_ERROR);
    });

    expect($outcomes->where('late', true))->toHaveCount(0, 'Un proces a pornit după barieră: scanările nu au fost simultane.')
        ->and($outcomes->where('status', 'ok'))->toHaveCount(1)
        ->and($outcomes->firstWhere('status', 'ok')['card_id'])->toBe($this->card->id)
        ->and($outcomes->where('status', 'rejected'))->toHaveCount(1)
        ->and($outcomes->firstWhere('status', 'rejected')['message'])->toBe('Cod QR deja folosit.');
});
