<?php

use App\Enums\BusinessStatus;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\User;
use App\Services\Loyalty\BusinessService;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
    $this->business = Business::factory()->create(['name' => 'Cafeneaua Suspectă', 'slug' => 'cafeneaua-suspecta']);
    $this->admin = User::factory()->platformAdmin()->create(['name' => 'Admin Platformă']);
    $this->owner = User::factory()->withRole($this->business, Role::Owner)->create();

    $this->suspend = fn (array $data = [], ?User $as = null) => $this->actingAs($as ?? $this->admin)
        ->from(route('admin.businesses.show', $this->business))
        ->post(route('admin.businesses.suspend', $this->business), $data);

    $this->reactivate = fn (array $data = [], ?User $as = null) => $this->actingAs($as ?? $this->admin)
        ->from(route('admin.businesses.show', $this->business))
        ->post(route('admin.businesses.reactivate', $this->business), $data);

    $this->audits = fn (string $action) => AuditLog::query()->where('action', $action)->get();
});

it('suspends a business with the reason in the audit log', function () {
    ($this->suspend)(['reason' => 'Ștampile false raportate'])
        ->assertRedirect(route('admin.businesses.show', $this->business))
        ->assertSessionHas('status', 'Afacerea a fost suspendată.');

    $log = ($this->audits)('business.suspended')->sole();

    expect($this->business->fresh()->status)->toBe(BusinessStatus::Suspended)
        ->and($log->business_id)->toBe($this->business->id)
        ->and($log->actor_id)->toBe($this->admin->id)
        ->and($log->payload)->toBe(['reason' => 'Ștampile false raportate'])
        ->and($log->ip_address)->toBeNull(); // ownerul vede jurnalul: fără IP-ul adminului
});

it('stores no payload when there is no reason', function () {
    ($this->suspend)(['reason' => '  ']);

    expect(($this->audits)('business.suspended')->sole()->payload)->toBeNull();
});

it('locks the owner and the customers out of a suspended business', function () {
    ($this->suspend)();

    $this->actingAs($this->owner)->get(route('dashboard.business.edit'))->assertForbidden();
    $this->get(route('customer.business', $this->business))->assertNotFound();
});

it('reactivates a suspended business', function () {
    ($this->suspend)();

    ($this->reactivate)()
        ->assertRedirect(route('admin.businesses.show', $this->business))
        ->assertSessionHas('status', 'Afacerea a fost reactivată.');

    expect($this->business->fresh()->isActive())->toBeTrue()
        ->and(($this->audits)('business.reactivated'))->toHaveCount(1);

    $this->actingAs($this->owner)->get(route('dashboard.business.edit'))->assertOk();
});

it('treats a repeated suspension or reactivation as a no-op', function () {
    ($this->suspend)();
    ($this->suspend)()->assertSessionHas('status', 'Afacerea era deja suspendată.');

    ($this->reactivate)();
    ($this->reactivate)()->assertSessionHas('status', 'Afacerea era deja activă.');

    expect(($this->audits)('business.suspended'))->toHaveCount(1)
        ->and(($this->audits)('business.reactivated'))->toHaveCount(1);
});

it('validates the reason', function () {
    ($this->suspend)(['reason' => str_repeat('a', 501)])->assertSessionHasErrors('reason');

    expect($this->business->fresh()->isActive())->toBeTrue();
});

it('shows the suspension in the business audit log once the owner is back', function () {
    ($this->suspend)(['reason' => 'Verificare']);
    ($this->reactivate)();

    $this->actingAs($this->owner)
        ->get(route('dashboard.audit.index'))
        ->assertOk()
        ->assertSee('Afacere suspendată')
        ->assertSee('Afacere reactivată')
        ->assertSee('Admin Platformă');
});

it('refuses non-admins in the service as well', function () {
    app(BusinessService::class)->suspend($this->owner, $this->business);
})->throws(AuthorizationException::class);
