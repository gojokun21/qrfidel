<?php

use App\Enums\Role;
use App\Exceptions\InvalidScanTicket;
use App\Models\Card;
use App\Models\User;
use App\Services\Loyalty\ScanTicket;
use App\Services\Loyalty\ScanTicketService;
use Illuminate\Support\Carbon;

beforeEach(function () {
    foreach (loyaltyFixture() as $key => $value) {
        $this->{$key} = $value;
    }

    $this->tickets = app(ScanTicketService::class);
    $this->ticket = $this->tickets->issue($this->card, $this->staff, $this->location);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('issues an opaque ticket bound to the card, the staff member and the location', function () {
    $found = $this->tickets->find($this->ticket, $this->card, $this->staff);

    expect($this->ticket)->toMatch('/^[A-Za-z0-9_-]{32,}$/')
        ->and($this->ticket)->not->toContain($this->card->public_id)
        ->and($found)->toBeInstanceOf(ScanTicket::class)
        ->and($found->location->is($this->location))->toBeTrue()
        ->and($found->stampUsed)->toBeFalse()
        ->and($found->redeemUsed)->toBeFalse();
});

it('issues a different ticket on every scan', function () {
    expect($this->tickets->issue($this->card, $this->staff, $this->location))->not->toBe($this->ticket);
});

it('refuses an unknown or malformed ticket', function (string $ticket) {
    expect(fn () => $this->tickets->find($ticket, $this->card, $this->staff))
        ->toThrow(InvalidScanTicket::class, 'Scanează din nou codul clientului.');
})->with(['', 'nope', str_repeat('a', 43), '../../etc/passwd']);

it('refuses the ticket for another card', function () {
    $other = Card::factory()->for($this->program)->create();

    expect(fn () => $this->tickets->find($this->ticket, $other, $this->staff))->toThrow(InvalidScanTicket::class);
});

it('refuses the ticket for another staff member', function () {
    $colleague = User::factory()->withRole($this->business, Role::Staff)->create();

    expect(fn () => $this->tickets->find($this->ticket, $this->card, $colleague))->toThrow(InvalidScanTicket::class);
});

it('expires after the configured time', function () {
    Carbon::setTestNow('2026-10-16 12:00:00');
    $ticket = $this->tickets->issue($this->card, $this->staff, $this->location);

    Carbon::setTestNow(now()->addSeconds(config('loyalty.scan.ticket_ttl_seconds') - 1));
    expect($this->tickets->find($ticket, $this->card, $this->staff)->expiresAt->isFuture())->toBeTrue();

    Carbon::setTestNow(now()->addSeconds(2));
    expect(fn () => $this->tickets->find($ticket, $this->card, $this->staff))->toThrow(InvalidScanTicket::class);
});

describe('claiming an action', function () {
    beforeEach(function () {
        $this->found = fn (): ScanTicket => $this->tickets->find($this->ticket, $this->card, $this->staff);
    });

    it('allows one stamp per scan and reports it as used', function () {
        expect($this->tickets->claim(($this->found)(), ScanTicketService::STAMP, 'key-1'))->toBeTrue()
            ->and(($this->found)()->stampUsed)->toBeTrue()
            ->and(fn () => $this->tickets->claim(($this->found)(), ScanTicketService::STAMP, 'key-2'))
            ->toThrow(InvalidScanTicket::class, 'Această scanare a fost deja folosită – scanează din nou codul clientului.');
    });

    it('lets the same idempotency key through again (double click), without claiming anew', function () {
        $this->tickets->claim(($this->found)(), ScanTicketService::STAMP, 'key-1');

        expect($this->tickets->claim(($this->found)(), ScanTicketService::STAMP, 'key-1'))->toBeFalse();
    });

    it('allows a stamp and a redeem on the same scan', function () {
        $this->tickets->claim(($this->found)(), ScanTicketService::STAMP, 'key-1');

        expect($this->tickets->claim(($this->found)(), ScanTicketService::REDEEM, 'key-2'))->toBeTrue()
            ->and(($this->found)()->redeemUsed)->toBeTrue();
    });

    it('frees the action again when it is released by the key that holds it', function () {
        $this->tickets->claim(($this->found)(), ScanTicketService::STAMP, 'key-1');
        $this->tickets->release(($this->found)(), ScanTicketService::STAMP, 'other-key');

        expect(($this->found)()->stampUsed)->toBeTrue();

        $this->tickets->release(($this->found)(), ScanTicketService::STAMP, 'key-1');

        expect(($this->found)()->stampUsed)->toBeFalse()
            ->and($this->tickets->claim(($this->found)(), ScanTicketService::STAMP, 'key-3'))->toBeTrue();
    });

    it('rejects an unknown action', function () {
        $this->tickets->claim(($this->found)(), 'void', 'key-1');
    })->throws(InvalidArgumentException::class);
});
