<?php

use App\Exceptions\InvalidQrToken;
use App\Services\Loyalty\QrTokenService;
use Illuminate\Cache\RedisStore;
use Illuminate\Support\Facades\Cache;

/*
 * Restul suitei folosește store-ul `array`; aici verific că jti-urile și codurile scurte merg pe Redis-ul real (predis).
 */

beforeEach(function () {
    config(['loyalty.qr.store' => 'redis']);

    foreach (loyaltyFixture() as $key => $value) {
        $this->{$key} = $value;
    }

    $this->qr = app(QrTokenService::class);
    $this->keys = [];

    /** @var RedisStore $store */
    $store = Cache::store('redis')->getStore();
    $this->ttlOf = function (string $key) use ($store): int {
        $this->keys[] = $key;

        return (int) $store->connection()->ttl($store->getPrefix().$key);
    };
});

afterEach(function () {
    foreach ([...$this->keys, "qr:code-of:{$this->card->id}"] as $key) {
        Cache::store('redis')->forget($key);
    }
});

it('consumes the jti in Redis with a 5 minute TTL', function () {
    $qr = $this->qr->issue($this->card);
    $jti = explode('.', $qr->token)[2];
    $this->keys[] = "qr:code:{$this->business->id}:{$qr->shortCode}";

    expect($this->qr->resolve($qr->token, $this->staff, $this->location)->is($this->card))->toBeTrue()
        ->and(($this->ttlOf)("qr:jti:{$jti}"))->toBeBetween(295, 300)
        ->and(fn () => $this->qr->resolve($qr->token, $this->staff, $this->location))
        ->toThrow(InvalidQrToken::class, 'Cod QR deja folosit.');
});

it('stores the short code in Redis for 90 seconds and removes it once used', function () {
    $qr = $this->qr->issue($this->card);
    $key = "qr:code:{$this->business->id}:{$qr->shortCode}";

    expect(($this->ttlOf)($key))->toBeBetween(85, 90)
        ->and($this->qr->resolve($qr->shortCode, $this->staff, $this->location)->is($this->card))->toBeTrue()
        ->and(Cache::store('redis')->has($key))->toBeFalse();

    ($this->ttlOf)('qr:jti:'.explode('.', $qr->token)[2]);
});
