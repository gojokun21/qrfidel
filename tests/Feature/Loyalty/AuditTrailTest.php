<?php

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\User;
use App\Services\Loyalty\AuditTrail;
use App\Services\Loyalty\ExpirationService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

it('stores the client IP for actions made through a web request', function () {
    $business = Business::factory()->create();
    $owner = User::factory()->withRole($business, Role::Owner)->create();

    $this->actingAs($owner)
        ->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
        ->post(route('dashboard.locations.store'), ['name' => 'Centru'])
        ->assertRedirect();

    expect(AuditLog::query()->where('action', 'location.created')->sole()->ip_address)->toBe('203.0.113.7');
});

it('stores no IP for actions made outside a request (jobs, console)', function () {
    $fixture = loyaltyFixture();
    giveReward($fixture['card'], Carbon::now()->subMinute());

    app(ExpirationService::class)->expireRewards();

    expect(AuditLog::query()->where('action', 'reward.expired')->sole()->ip_address)->toBeNull();
});

it('records system actions without an actor', function () {
    $fixture = loyaltyFixture();

    $log = app(AuditTrail::class)->record(null, $fixture['card'], 'card.recalculated', $fixture['card']);

    expect($log->actor_type)->toBeNull()
        ->and($log->actor_id)->toBeNull()
        ->and($log->business_id)->toBe($fixture['business']->id);
});

describe('audit_logs is append-only', function () {
    beforeEach(function () {
        $this->log = AuditLog::factory()->create();
    });

    it('refuses model updates', function () {
        $this->log->update(['action' => 'card.redeemed']);
    })->throws(LogicException::class);

    it('refuses model deletes', function () {
        $this->log->delete();
    })->throws(LogicException::class);

    it('refuses updates through the query builder', function () {
        inSavepoint(fn () => DB::table('audit_logs')->where('id', $this->log->id)->update(['action' => 'x.y']));
    })->throws(QueryException::class);

    it('refuses deletes through the query builder', function () {
        inSavepoint(fn () => DB::table('audit_logs')->where('id', $this->log->id)->delete());
    })->throws(QueryException::class);
});
