<?php

use App\Exceptions\InvalidQrToken;
use App\Services\Loyalty\QrTokenService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/*
 * Hostingul `loyup.ro` nu are Redis (ADR 022): jti-urile, codurile scurte, tichetele și nonce-urile stau în MySQL,
 * în tabelul `loyalty_cache`, separat de cache-ul aplicației (golit de cache:clear).
 */

beforeEach(function () {
    config(['loyalty.qr.store' => 'loyalty_database']);

    foreach (loyaltyFixture() as $key => $value) {
        $this->{$key} = $value;
    }

    $this->qr = app(QrTokenService::class);
});

it('consumes the jti once in the loyalty_cache table', function () {
    $qr = $this->qr->issue($this->card);
    $jti = explode('.', $qr->token)[2];

    expect($this->qr->resolve($qr->token, $this->staff, $this->location)->is($this->card))->toBeTrue()
        ->and(DB::table('loyalty_cache')->where('key', 'like', "%qr:jti:{$jti}")->exists())->toBeTrue()
        ->and(fn () => $this->qr->resolve($qr->token, $this->staff, $this->location))
        ->toThrow(InvalidQrToken::class, 'Cod QR deja folosit.');
});

it('resolves the short code from MySQL and removes it once used', function () {
    $qr = $this->qr->issue($this->card);

    expect($this->qr->resolve($qr->shortCode, $this->staff, $this->location)->is($this->card))->toBeTrue()
        ->and(Cache::store('loyalty_database')->has("qr:code:{$this->business->id}:{$qr->shortCode}"))->toBeFalse();
});

it('adds a key only once, like SET NX', function () {
    $store = Cache::store('loyalty_database');

    expect($store->add('once', 'a', 60))->toBeTrue()
        ->and($store->add('once', 'b', 60))->toBeFalse()
        ->and($store->get('once'))->toBe('a');
});

it('keeps used codes when the application cache is cleared', function () {
    config(['cache.default' => 'database']);
    Cache::store('loyalty_database')->put('kept', 'yes', 60);

    Artisan::call('cache:clear');

    expect(Cache::store('loyalty_database')->get('kept'))->toBe('yes');
});

it('prunes expired rows from both cache tables', function () {
    $past = now()->subMinute()->getTimestamp();
    $future = now()->addHour()->getTimestamp();

    DB::table('loyalty_cache')->insert([
        ['key' => 'old', 'value' => 's:1:"x";', 'expiration' => $past],
        ['key' => 'fresh', 'value' => 's:1:"x";', 'expiration' => $future],
    ]);
    DB::table('cache')->insert([
        ['key' => 'old', 'value' => 's:1:"x";', 'expiration' => $past],
        ['key' => 'fresh', 'value' => 's:1:"x";', 'expiration' => $future],
    ]);

    $this->artisan('cache:prune-expired')->assertSuccessful();

    expect(DB::table('loyalty_cache')->pluck('key')->all())->toBe(['fresh'])
        ->and(DB::table('cache')->pluck('key')->all())->toBe(['fresh']);
});

it('schedules the pruning', function () {
    $this->artisan('schedule:list')->expectsOutputToContain('cache:prune-expired');
});
