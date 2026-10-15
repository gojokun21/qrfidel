<?php

use Illuminate\Support\Facades\DB;

it('runs the test suite against PostgreSQL', function () {
    expect(DB::connection()->getDriverName())->toBe('pgsql')
        ->and(DB::connection()->getDatabaseName())->toStartWith('creative_loyalty_test');
});

it('renders the start page', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee(config('app.name'));
});
