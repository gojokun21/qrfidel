<?php

use Illuminate\Support\Facades\DB;

it('runs the test suite against PostgreSQL', function () {
    expect(DB::connection()->getDriverName())->toBe('pgsql')
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
