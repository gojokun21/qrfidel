<?php

use App\Enums\TransactionType;
use App\Models\CardTransaction;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\Process;

/*
 * Două procese PHP reale ștampilează același card în același moment. Datele sunt commit-uite
 * (DatabaseTruncation, vezi tests/Pest.php), ca procesele să le vadă pe conexiunile lor.
 */

beforeEach(function () {
    foreach (loyaltyFixture(['cooldown_minutes' => 30]) as $key => $value) {
        $this->{$key} = $value;
    }

    $this->race = function (string $firstKey, string $secondKey): array {
        $startAt = sprintf('%.6F', microtime(true) + 3);
        $env = [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'mysql',
            'DB_DATABASE' => config('database.connections.mysql.database'),
            'DB_URL' => '',
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
        ];
        $worker = fn (string $key) => [
            PHP_BINARY, base_path('tests/Concurrency/stamp-worker.php'),
            (string) $this->card->id, (string) $this->staff->id, (string) $this->location->id, $key, $startAt,
        ];

        $results = Process::pool(function (Pool $pool) use ($worker, $env, $firstKey, $secondKey) {
            $pool->env($env)->timeout(60)->command($worker($firstKey));
            $pool->env($env)->timeout(60)->command($worker($secondKey));
        })->start()->wait();

        return $results->collect()->map(function ($result) {
            expect($result->successful())->toBeTrue($result->errorOutput().$result->output());

            return json_decode($result->output(), true, flags: JSON_THROW_ON_ERROR);
        })->all();
    };
});

afterEach(function () {
    $this->truncateTablesForAllConnections();
});

it('produces a single stamp when two parallel requests hit an active cooldown', function () {
    $outcomes = collect(($this->race)('race-a', 'race-b'));

    expect($outcomes->where('status', 'ok'))->toHaveCount(1)
        ->and($outcomes->where('status', 'violation'))->toHaveCount(1)
        ->and($outcomes->firstWhere('status', 'violation')['message'])->toStartWith('Ultima ștampilă acum')
        ->and(CardTransaction::where('card_id', $this->card->id)->where('type', TransactionType::Stamp)->count())->toBe(1)
        ->and($this->card->fresh()->stamps_balance)->toBe(1);
});

it('produces a single transaction when the same idempotency key arrives twice in parallel', function () {
    $outcomes = collect(($this->race)('same-key', 'same-key'));

    expect($outcomes->where('status', 'ok'))->toHaveCount(2)
        ->and($outcomes->pluck('transaction_id')->unique())->toHaveCount(1)
        ->and($outcomes->where('replayed', true))->toHaveCount(1)
        ->and(CardTransaction::where('card_id', $this->card->id)->count())->toBe(1)
        ->and($this->card->fresh()->stamps_balance)->toBe(1);
});
