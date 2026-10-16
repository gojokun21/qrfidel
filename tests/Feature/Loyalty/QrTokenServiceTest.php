<?php

use App\Enums\BusinessStatus;
use App\Enums\ProgramStatus;
use App\Enums\Role;
use App\Exceptions\InvalidQrToken;
use App\Models\AuditLog;
use App\Models\Card;
use App\Models\Customer;
use App\Models\Location;
use App\Models\User;
use App\Services\Loyalty\QrToken;
use App\Services\Loyalty\QrTokenService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

beforeEach(function () {
    foreach (loyaltyFixture() as $key => $value) {
        $this->{$key} = $value;
    }

    $this->qr = app(QrTokenService::class);
    $this->issue = fn (?Card $card = null): QrToken => $this->qr->issue($card ?? $this->card);
    $this->resolve = fn (string $input, ?User $actor = null, ?Location $location = null): Card => $this->qr->resolve(
        $input,
        $actor ?? $this->staff,
        $location ?? $this->location,
    );

    // Înlocuiește o parte din token (0 = public_id, 1 = timestamp, 2 = jti, 3 = semnătura).
    $this->tamper = function (string $token, int $part, string $value): string {
        $parts = explode('.', $token);
        $parts[$part] = $value;

        return implode('.', $parts);
    };

    $this->jtiOf = fn (string $token): string => explode('.', $token)[2];
    $this->jtiUsed = fn (string $token): bool => Cache::store(config('loyalty.qr.store'))->has('qr:jti:'.($this->jtiOf)($token));
});

afterEach(function () {
    Carbon::setTestNow();
});

describe('issuing', function () {
    it('issues a token in the format public_id.timestamp.jti.signature', function () {
        Carbon::setTestNow('2026-10-15 12:00:00');

        $qr = ($this->issue)();
        $parts = explode('.', $qr->token);

        expect($parts)->toHaveCount(4)
            ->and($parts[0])->toBe($this->card->public_id)
            ->and($parts[1])->toBe((string) now()->getTimestamp())
            ->and($parts[2])->toMatch('/^[A-Za-z0-9_-]{16}$/')
            ->and($parts[3])->toMatch('/^[A-Za-z0-9_-]{43}$/')
            ->and($qr->issuedAt->equalTo(now()))->toBeTrue()
            ->and($qr->expiresAt->equalTo(now()->addSeconds(90)))->toBeTrue();
    });

    it('issues a 6-character short code without ambiguous characters', function () {
        $qr = ($this->issue)();

        expect($qr->shortCode)->toMatch('/^[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{6}$/');
    });

    it('issues a different jti and signature on every rotation', function () {
        $first = ($this->issue)();
        $second = ($this->issue)();

        expect($first->token)->not->toBe($second->token)
            ->and(($this->jtiOf)($first->token))->not->toBe(($this->jtiOf)($second->token))
            ->and($first->shortCode)->not->toBe($second->shortCode);
    });

    it('revokes the previous short code after a short grace period, so a code being dictated still works', function () {
        Carbon::setTestNow('2026-10-16 12:00:00');
        $first = ($this->issue)();
        $second = ($this->issue)();
        $third = ($this->issue)();

        Carbon::setTestNow(now()->addSeconds(config('loyalty.qr.short_code_grace_seconds') - 1));
        expect(($this->resolve)($second->shortCode)->is($this->card))->toBeTrue();

        Carbon::setTestNow(now()->addSeconds(2));
        expect(fn () => ($this->resolve)($first->shortCode))->toThrow(InvalidQrToken::class)
            ->and(($this->resolve)($third->shortCode)->is($this->card))->toBeTrue()
            ->and(($this->resolve)($first->token)->is($this->card))->toBeTrue();
    });

    it('never extends the life of a previous code beyond its own validity', function () {
        Carbon::setTestNow('2026-10-16 12:00:00');
        $first = ($this->issue)();

        Carbon::setTestNow(now()->addSeconds(config('loyalty.qr.ttl_seconds') - 5));
        ($this->issue)();

        // Tokenul încă e valabil (vârsta = ttl), dar codul lui scurt nu primește grație peste valabilitate.
        Carbon::setTestNow(now()->addSeconds(5));
        expect(fn () => ($this->resolve)($first->shortCode))->toThrow(InvalidQrToken::class, 'Cod invalid sau expirat')
            ->and(($this->resolve)($first->token)->is($this->card))->toBeTrue();
    });

    it('does not expose the card secret in the token', function () {
        $qr = ($this->issue)();

        expect($qr->token)->not->toContain($this->card->secret)
            ->and($qr->shortCode)->not->toContain($this->card->secret);
    });
});

describe('resolving a valid token', function () {
    it('resolves the token to its card', function () {
        $card = ($this->resolve)(($this->issue)()->token);

        expect($card->is($this->card))->toBeTrue();
    });

    it('resolves the short code to the same card', function () {
        $card = ($this->resolve)(($this->issue)()->shortCode);

        expect($card->is($this->card))->toBeTrue();
    });

    it('accepts a short code typed in lowercase, with spaces or a dash', function () {
        $code = ($this->issue)()->shortCode;
        $typed = ' '.strtolower(substr($code, 0, 3)).'-'.strtolower(substr($code, 3)).' ';

        expect(($this->resolve)($typed)->is($this->card))->toBeTrue();
    });

    it('accepts surrounding whitespace around the token', function () {
        expect(($this->resolve)('  '.($this->issue)()->token."\n")->is($this->card))->toBeTrue();
    });

    it('works for owners, managers of the location and unrestricted staff', function () {
        foreach ([$this->owner, $this->manager, $this->staff] as $actor) {
            expect(($this->resolve)(($this->issue)()->token, $actor)->is($this->card))->toBeTrue();
        }
    });

    it('keeps tokens from different rotations independent', function () {
        $first = ($this->issue)();
        Carbon::setTestNow(now()->addSeconds(30));
        $second = ($this->issue)();

        ($this->resolve)($second->token);

        expect(($this->resolve)($first->token)->is($this->card))->toBeTrue();
    });

    it('finds the card regardless of the current business context', function () {
        inBusiness(loyaltyFixture()['business']);

        expect(($this->resolve)(($this->issue)()->token)->is($this->card))->toBeTrue();
    });
});

describe('forged tokens', function () {
    it('rejects a token whose :dataset part was changed', function (int $part, Closure $value) {
        $token = ($this->issue)()->token;
        $forged = ($this->tamper)($token, $part, $value(explode('.', $token)[$part], $this));

        expect(fn () => ($this->resolve)($forged))->toThrow(InvalidQrToken::class, 'Cod QR invalid.')
            ->and(($this->jtiUsed)($forged))->toBeFalse();
    })->with([
        'signature' => [3, fn (string $sig) => strrev($sig)],
        'timestamp' => [1, fn (string $ts) => (string) ((int) $ts - 1)],
        'jti' => [2, fn (string $jti) => strrev($jti)],
        'public_id' => [0, fn (string $id, $test) => Card::factory()->for($test->program)->create()->public_id],
    ]);

    it('rejects a token signed with another card secret', function () {
        $other = Card::factory()->for($this->program)->create();
        $forged = ($this->tamper)(($this->issue)($other)->token, 0, $this->card->public_id);

        expect(fn () => ($this->resolve)($forged))->toThrow(InvalidQrToken::class, 'Cod QR invalid.');
    });

    it('rejects a token for a card that does not exist', function () {
        $forged = ($this->tamper)(($this->issue)()->token, 0, strtolower((string) Str::ulid()));

        expect(fn () => ($this->resolve)($forged))->toThrow(InvalidQrToken::class, 'Cod QR invalid.');
    });

    it('rejects malformed input: :dataset', function (string $input) {
        expect(fn () => ($this->resolve)($input))->toThrow(InvalidQrToken::class, 'Cod QR invalid.');
    })->with([
        'empty' => '',
        'spaces' => '   ',
        'garbage' => 'not-a-token',
        'too few parts' => 'a.b.c',
        'too many parts' => 'a.b.c.d.e',
        'non-numeric timestamp' => '01jabcdefghjkmnpqrstvwxyz0.abc.AAAAAAAAAAAAAAAA.'.str_repeat('A', 43),
        'short code too short' => 'ABC',
        'url' => 'https://example.com/?x=1',
    ]);

    it('tells staff to ask for the current code when a short code is unknown or expired', function () {
        expect(fn () => ($this->resolve)('ZZZZZZ'))
            ->toThrow(InvalidQrToken::class, 'Cod invalid sau expirat – cere clientului codul actual.');
    });

    it('rejects a token with the signature stripped', function () {
        $token = ($this->issue)()->token;

        expect(fn () => ($this->resolve)(substr($token, 0, strrpos($token, '.'))))->toThrow(InvalidQrToken::class);
    });
});

describe('expiry', function () {
    it('accepts a token that is exactly 90 seconds old', function () {
        Carbon::setTestNow('2026-10-15 12:00:00');
        $token = ($this->issue)()->token;

        Carbon::setTestNow(now()->addSeconds(90));

        expect(($this->resolve)($token)->is($this->card))->toBeTrue();
    });

    it('rejects a token older than 90 seconds, a screenshot does not work', function () {
        Carbon::setTestNow('2026-10-15 12:00:00');
        $token = ($this->issue)()->token;

        Carbon::setTestNow(now()->addSeconds(91));

        expect(fn () => ($this->resolve)($token))
            ->toThrow(InvalidQrToken::class, 'Cod QR expirat – cere clientului să-l reîmprospăteze.')
            ->and(($this->jtiUsed)($token))->toBeFalse();
    });

    it('rejects an expired short code', function () {
        Carbon::setTestNow('2026-10-15 12:00:00');
        $code = ($this->issue)()->shortCode;

        Carbon::setTestNow(now()->addSeconds(91));

        expect(fn () => ($this->resolve)($code))
            ->toThrow(InvalidQrToken::class, 'Cod invalid sau expirat – cere clientului codul actual.');
    });

    it('rejects a token issued in the future beyond the allowed clock skew', function () {
        Carbon::setTestNow('2026-10-15 12:01:00');
        $token = ($this->issue)()->token;

        Carbon::setTestNow('2026-10-15 12:00:00');

        expect(fn () => ($this->resolve)($token))->toThrow(InvalidQrToken::class, 'Cod QR invalid.');
    });

    it('tolerates a few seconds of clock skew', function () {
        Carbon::setTestNow('2026-10-15 12:00:05');
        $token = ($this->issue)()->token;

        Carbon::setTestNow('2026-10-15 12:00:00');

        expect(($this->resolve)($token)->is($this->card))->toBeTrue();
    });
});

describe('single use', function () {
    it('rejects a token that was already used', function () {
        $token = ($this->issue)()->token;
        ($this->resolve)($token);

        expect(fn () => ($this->resolve)($token))->toThrow(InvalidQrToken::class, 'Cod QR deja folosit.');
    });

    it('rejects a reused token even at another location or by another staff member', function () {
        $token = ($this->issue)()->token;
        ($this->resolve)($token);

        expect(fn () => ($this->resolve)($token, $this->owner, $this->otherLocation))
            ->toThrow(InvalidQrToken::class, 'Cod QR deja folosit.');
    });

    it('rejects the short code once the token was used, by either form', function () {
        $qr = ($this->issue)();
        ($this->resolve)($qr->token);

        expect(fn () => ($this->resolve)($qr->shortCode))->toThrow(InvalidQrToken::class);

        $second = ($this->issue)();
        ($this->resolve)($second->shortCode);

        expect(fn () => ($this->resolve)($second->shortCode))->toThrow(InvalidQrToken::class)
            ->and(fn () => ($this->resolve)($second->token))->toThrow(InvalidQrToken::class, 'Cod QR deja folosit.');
    });

    it('keeps the used jti for 5 minutes', function () {
        Carbon::setTestNow('2026-10-15 12:00:00');
        $token = ($this->issue)()->token;
        ($this->resolve)($token);

        Carbon::setTestNow(now()->addSeconds(299));
        expect(($this->jtiUsed)($token))->toBeTrue();

        Carbon::setTestNow(now()->addSeconds(2));
        expect(($this->jtiUsed)($token))->toBeFalse();
    });
});

it('keeps the jti at least as long as the token is valid, whatever the config says', function () {
    config(['loyalty.qr.ttl_seconds' => 600, 'loyalty.qr.used_ttl_seconds' => 60]);
    Carbon::setTestNow('2026-10-15 12:00:00');
    $token = ($this->issue)()->token;
    ($this->resolve)($token);

    Carbon::setTestNow(now()->addSeconds(600));

    expect(fn () => ($this->resolve)($token))->toThrow(InvalidQrToken::class, 'Cod QR deja folosit.');
});

describe('secret rotation', function () {
    it('invalidates every outstanding token and short code', function () {
        $qr = ($this->issue)();
        $other = ($this->issue)();

        $this->qr->rotateSecret($this->card, $this->owner);

        expect(fn () => ($this->resolve)($qr->token))->toThrow(InvalidQrToken::class, 'Cod QR invalid.')
            ->and(fn () => ($this->resolve)($other->shortCode))->toThrow(InvalidQrToken::class, 'Cod QR invalid.');
    });

    it('issues working tokens with the new secret', function () {
        $oldSecret = $this->card->secret;
        $this->qr->rotateSecret($this->card, $this->owner);

        expect($this->card->fresh()->secret)->not->toBe($oldSecret)
            ->and(($this->resolve)(($this->issue)($this->card->fresh())->token)->is($this->card))->toBeTrue();
    });

    it('writes an audit entry without the secret', function () {
        $this->qr->rotateSecret($this->card, $this->owner);

        $log = AuditLog::where('action', 'card.secret_rotated')->sole();
        expect($log->actor_id)->toBe($this->owner->id)
            ->and($log->business_id)->toBe($this->business->id)
            ->and(json_encode($log->payload))->not->toContain($this->card->fresh()->secret);
    });

    it('refuses a rotation by staff or by a member of another business, leaving the secret unchanged', function () {
        $secret = $this->card->secret;

        expect(fn () => $this->qr->rotateSecret($this->card, $this->staff))->toThrow(AuthorizationException::class)
            ->and(fn () => $this->qr->rotateSecret($this->card, loyaltyFixture()['owner']))->toThrow(AuthorizationException::class)
            ->and($this->card->fresh()->secret)->toBe($secret)
            ->and(AuditLog::where('action', 'card.secret_rotated')->count())->toBe(0);
    });

    it('lets the manager rotate the secret', function () {
        $secret = $this->card->secret;
        $this->qr->rotateSecret($this->card, $this->manager);

        expect($this->card->fresh()->secret)->not->toBe($secret);
    });

    it('rotates without an audit entry when there is no actor (e.g. customer logout)', function () {
        $this->qr->rotateSecret($this->card);

        expect(AuditLog::where('action', 'card.secret_rotated')->count())->toBe(0);
    });
});

describe('tenancy and authorization', function () {
    it('refuses staff from business X scanning a card from business Y', function () {
        $other = loyaltyFixture();
        $token = ($this->issue)($other['card'])->token;

        expect(fn () => ($this->resolve)($token))->toThrow(AuthorizationException::class)
            ->and(($this->jtiUsed)($token))->toBeFalse();
    });

    it('refuses a location from another business even for a member of the card business', function () {
        $other = loyaltyFixture();
        $token = ($this->issue)()->token;

        expect(fn () => ($this->resolve)($token, $this->owner, $other['location']))->toThrow(AuthorizationException::class)
            ->and(($this->jtiUsed)($token))->toBeFalse();
    });

    it('refuses a non-member', function () {
        $token = ($this->issue)()->token;

        expect(fn () => ($this->resolve)($token, User::factory()->create()))->toThrow(AuthorizationException::class);
    });

    it('refuses staff bound to another location of the same business', function () {
        $boundStaff = User::factory()->withRole($this->business, Role::Staff, $this->otherLocation)->create();
        $token = ($this->issue)()->token;

        expect(fn () => ($this->resolve)($token, $boundStaff))->toThrow(AuthorizationException::class)
            ->and(($this->resolve)($token, $boundStaff, $this->otherLocation)->is($this->card))->toBeTrue();
    });

    it('does not resolve a short code issued for another business', function () {
        $other = loyaltyFixture();
        $code = ($this->issue)($other['card'])->shortCode;

        expect(fn () => ($this->resolve)($code))->toThrow(InvalidQrToken::class, 'Cod invalid sau expirat – cere clientului codul actual.')
            ->and(($this->resolve)($code, $other['staff'], $other['location'])->is($other['card']))->toBeTrue();
    });

    it('does not burn the token when the scan is refused', function () {
        $token = ($this->issue)()->token;

        expect(fn () => ($this->resolve)($token, User::factory()->create()))->toThrow(AuthorizationException::class)
            ->and(($this->resolve)($token)->is($this->card))->toBeTrue();
    });
});

describe('program and business state', function () {
    it('rejects a card whose program is not active, without burning the token', function (ProgramStatus $status) {
        $token = ($this->issue)()->token;
        $this->program->update(['status' => $status]);

        expect(fn () => ($this->resolve)($token))->toThrow(InvalidQrToken::class, 'Programul nu este activ.')
            ->and(($this->jtiUsed)($token))->toBeFalse();

        $this->program->update(['status' => ProgramStatus::Active]);

        expect(($this->resolve)($token)->is($this->card))->toBeTrue();
    })->with([ProgramStatus::Paused, ProgramStatus::Archived, ProgramStatus::Draft]);

    it('rejects a card of a suspended business', function () {
        $token = ($this->issue)()->token;
        $this->business->status = BusinessStatus::Suspended;
        $this->business->save();

        expect(fn () => ($this->resolve)($token, $this->owner))->toThrow(InvalidQrToken::class, 'Afacerea este suspendată.')
            ->and(($this->jtiUsed)($token))->toBeFalse();
    });
});

describe('issue for the customer (PWA)', function () {
    it('issues a token that resolves when the card belongs to the customer', function () {
        $qr = $this->qr->issueForCustomer($this->card, $this->card->customer);

        expect(($this->resolve)($qr->token)->is($this->card))->toBeTrue();
    });

    it('refuses the card of another customer', function () {
        expect(fn () => $this->qr->issueForCustomer($this->card, Customer::factory()->create()))
            ->toThrow(AuthorizationException::class);
    });

    it('tells whether the card can be scanned now', function () {
        expect($this->qr->isScannable($this->card))->toBeTrue();

        $this->program->update(['status' => ProgramStatus::Paused]);

        expect($this->qr->isScannable($this->card->refresh()))->toBeFalse();
    });

    it('refuses a program that is not active', function (ProgramStatus $status) {
        $this->program->update(['status' => $status]);

        expect(fn () => $this->qr->issueForCustomer($this->card, $this->card->customer))
            ->toThrow(InvalidQrToken::class, 'Programul nu este activ.');
    })->with([ProgramStatus::Paused, ProgramStatus::Archived, ProgramStatus::Draft]);

    it('refuses a suspended business', function () {
        $this->business->status = BusinessStatus::Suspended;
        $this->business->save();

        expect(fn () => $this->qr->issueForCustomer($this->card, $this->card->customer))
            ->toThrow(InvalidQrToken::class, 'Afacerea este suspendată.');
    });
});
