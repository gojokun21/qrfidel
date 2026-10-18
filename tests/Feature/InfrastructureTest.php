<?php

use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\ParallelTesting;

it('runs the test suite against MySQL 8, like the hosting', function () {
    expect(DB::connection()->getDriverName())->toBe('mysql')
        ->and(DB::scalar('select version()'))->toStartWith('8.0.')
        ->and(DB::scalar('select @@transaction_isolation'))->toBe('READ-COMMITTED')
        ->and(config('database.connections.mysql.collation'))->toBe('utf8mb4_0900_as_cs')
        ->and(DB::connection()->getDatabaseName())->toStartWith('creative_loyalty_test');
});

it('renders the start page', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('LoyUp');
});

it('uses LoyUp as the application name in the page title', function () {
    expect(config('app.name'))->toBe('LoyUp');

    $this->get('/')
        ->assertOk()
        ->assertSee('· LoyUp</title>', false);
});

it('migrates again when a parallel worker gets its test database recreated', function () {
    // Laravel șterge și recreează baza unui worker la orice eroare de conexiune (TestDatabases::ensureTestDatabaseExists);
    // fără reset, RefreshDatabase crede că schema există și testele pică cu „relation ... does not exist”.
    $callbacks = (fn () => $this->setUpTestDatabaseCallbacks)->call(app(ParallelTesting::class));

    RefreshDatabaseState::$migrated = true;
    foreach ($callbacks as $callback) {
        app()->call($callback, ['database' => 'creative_loyalty_test_test_1', 'token' => '1']);
    }

    expect($callbacks)->not->toBeEmpty()
        ->and(RefreshDatabaseState::$migrated)->toBeFalse();
});
