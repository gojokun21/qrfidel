<?php

use App\Enums\ProgramStatus;
use App\Enums\Role;
use App\Exceptions\InvalidScanTicket;
use App\Exceptions\RewardUnavailable;
use App\Exceptions\StampRuleViolation;
use App\Models\CardTransaction;
use App\Models\User;
use App\Services\Loyalty\ScanTicketService;
use App\Services\Loyalty\StaffCardActions;
use App\Services\Loyalty\StampService;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
    foreach (loyaltyFixture(['stamps_required' => 3, 'max_stamps_per_visit' => 2]) as $key => $value) {
        $this->{$key} = $value;
    }

    inBusiness($this->business);
    $this->tickets = app(ScanTicketService::class);
    $this->actions = app(StaffCardActions::class);
    $this->ticket = $this->tickets->issue($this->card, $this->staff, $this->location);
});

it('stamps the scanned card at the location of the scan', function () {
    $result = $this->actions->stamp($this->card, $this->staff, $this->ticket, 1, 'key-1');

    expect($result->card->stamps_balance)->toBe(1)
        ->and($result->transaction->location_id)->toBe($this->location->id)
        ->and($result->transaction->staff_user_id)->toBe($this->staff->id);
});

it('replays a double click with the same key: one transaction', function () {
    $this->actions->stamp($this->card, $this->staff, $this->ticket, 1, 'key-1');
    $again = $this->actions->stamp($this->card, $this->staff, $this->ticket, 1, 'key-1');

    expect($again->replayed)->toBeTrue()
        ->and(CardTransaction::query()->where('card_id', $this->card->id)->count())->toBe(1);
});

it('refuses a second stamp on the same scan', function () {
    $this->actions->stamp($this->card, $this->staff, $this->ticket, 1, 'key-1');

    expect(fn () => $this->actions->stamp($this->card, $this->staff, $this->ticket, 1, 'key-2'))
        ->toThrow(InvalidScanTicket::class)
        ->and($this->card->refresh()->stamps_balance)->toBe(1);
});

it('refuses to act without a valid scan', function () {
    expect(fn () => $this->actions->stamp($this->card, $this->staff, 'forged-ticket', 1, 'key-1'))
        ->toThrow(InvalidScanTicket::class)
        ->and(fn () => $this->actions->redeem($this->card, $this->staff, 'forged-ticket', 'key-2'))
        ->toThrow(InvalidScanTicket::class);
});

it('refuses the ticket of another staff member', function () {
    $colleague = User::factory()->withRole($this->business, Role::Staff)->create();

    expect(fn () => $this->actions->stamp($this->card, $colleague, $this->ticket, 1, 'key-1'))
        ->toThrow(InvalidScanTicket::class);
});

it('frees the scan when a rule refuses the stamp, so the staff member can retry', function () {
    expect(fn () => $this->actions->stamp($this->card, $this->staff, $this->ticket, 3, 'key-1'))
        ->toThrow(StampRuleViolation::class, 'Maxim 2 ștampile pe vizită.');

    expect($this->actions->stamp($this->card, $this->staff, $this->ticket, 2, 'key-2')->card->stamps_balance)->toBe(2);
});

it('does not free the scan when a repeated key fails after the stamp went through', function () {
    $this->actions->stamp($this->card, $this->staff, $this->ticket, 1, 'key-1');

    // Aceeași cheie cu altă cantitate = conflict; slotul rămâne al primei ștampile.
    expect(fn () => $this->actions->stamp($this->card, $this->staff, $this->ticket, 2, 'key-1'))->toThrow(Exception::class)
        ->and(fn () => $this->actions->stamp($this->card, $this->staff, $this->ticket, 1, 'key-3'))->toThrow(InvalidScanTicket::class);
});

it('keeps the scan claimed after a transient failure, so a parallel retry cannot leave room for a second stamp', function () {
    $this->mock(StampService::class)->shouldReceive('add')->andThrow(new RuntimeException('deadlock detected'));

    expect(fn () => app(StaffCardActions::class)->stamp($this->card, $this->staff, $this->ticket, 1, 'key-1'))
        ->toThrow(RuntimeException::class)
        ->and($this->tickets->find($this->ticket, $this->card, $this->staff)->stampUsed)->toBeTrue();
});

it('stamps the reward and redeems it on the same scan', function () {
    $this->card->forceFill(['stamps_balance' => 2])->save();

    $stamp = $this->actions->stamp($this->card, $this->staff, $this->ticket, 1, 'key-1');
    $redeem = $this->actions->redeem($this->card, $this->staff, $this->ticket, 'key-2');

    expect($stamp->rewardsIssued)->toHaveCount(1)
        ->and($redeem->reward->is($stamp->rewardsIssued[0]))->toBeTrue()
        ->and($redeem->card->rewards_available)->toBe(0)
        ->and($redeem->transaction->location_id)->toBe($this->location->id);
});

it('frees the scan when there is no reward to redeem', function () {
    expect(fn () => $this->actions->redeem($this->card, $this->staff, $this->ticket, 'key-1'))
        ->toThrow(RewardUnavailable::class);

    expect($this->tickets->find($this->ticket, $this->card, $this->staff)->redeemUsed)->toBeFalse();
});

it('redeems but does not stamp on a paused program', function () {
    $this->card->forceFill(['stamps_balance' => 2])->save();
    $this->actions->stamp($this->card, $this->staff, $this->ticket, 1, 'key-1');
    $this->program->update(['status' => ProgramStatus::Paused]);
    $ticket = $this->tickets->issue($this->card, $this->staff, $this->location);

    expect(fn () => $this->actions->stamp($this->card, $this->staff, $ticket, 1, 'key-2'))
        ->toThrow(StampRuleViolation::class, 'Programul nu este activ.')
        ->and($this->actions->redeem($this->card, $this->staff, $ticket, 'key-3')->card->rewards_available)->toBe(0);
});

it('keeps a staff member bound to a location from acting on a scan made elsewhere', function () {
    $bound = User::factory()->withRole($this->business, Role::Staff, $this->otherLocation)->create();
    $ticket = $this->tickets->issue($this->card, $bound, $this->location);

    expect(fn () => $this->actions->stamp($this->card, $bound, $ticket, 1, 'key-1'))
        ->toThrow(AuthorizationException::class, 'Poți lucra doar în locația ta.');
});
