<?php

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Customer;
use App\Models\User;
use App\Support\AuditActions;
use Illuminate\Database\Eloquent\Model;

beforeEach(function () {
    $this->business = Business::factory()->create();
    $this->owner = User::factory()->withRole($this->business, Role::Owner)->create(['name' => 'Olivia Owner']);

    $this->log = fn (string $action, ?Model $actor = null, ?Business $business = null) => AuditLog::factory()->create([
        'business_id' => ($business ?? $this->business)->id,
        'actor_type' => $actor?->getMorphClass(),
        'actor_id' => $actor?->getKey(),
        'action' => $action,
        'payload' => null,
    ]);
});

it('is only for owners', function (Role $role) {
    $user = User::factory()->withRole($this->business, $role)->create();

    $this->actingAs($user)->get(route('dashboard.audit.index'))->assertForbidden();
})->with([Role::Manager, Role::Staff]);

it('lists the actions of the current business with readable labels and actors', function () {
    $barista = User::factory()->withRole($this->business, Role::Staff)->create(['name' => 'Ion Barista']);
    $customer = Customer::factory()->create(['email' => 'ana.popescu@client.test']);

    ($this->log)('card.stamped', $barista);
    ($this->log)('stamp_request.created', $customer);
    ($this->log)('reward.expired');
    ($this->log)('location.created', $this->owner, Business::factory()->create());

    $this->actingAs($this->owner)
        ->get(route('dashboard.audit.index'))
        ->assertOk()
        ->assertSee('Jurnal de activitate')
        ->assertSee('Ștampilă adăugată')
        ->assertSee('Ion Barista')
        ->assertSee('Cerere de ștampilă trimisă')
        ->assertSee('an***@client.test')
        ->assertDontSee('ana.popescu@client.test')
        ->assertSee('Recompense expirate')
        ->assertSee('Sistem')
        ->assertDontSee('Locație creată');
});

it('shows the IP address', function () {
    AuditLog::factory()->create(['business_id' => $this->business->id, 'action' => 'card.stamped', 'ip_address' => '203.0.113.7']);

    $this->actingAs($this->owner)->get(route('dashboard.audit.index'))->assertSee('203.0.113.7');
});

it('filters by category', function () {
    ($this->log)('card.stamped');
    ($this->log)('member.invited');

    $this->actingAs($this->owner)
        ->get(route('dashboard.audit.index', ['category' => 'team']))
        ->assertOk()
        ->assertSee('Invitație trimisă')
        ->assertDontSee('Ștampilă adăugată');
});

it('ignores an unknown category', function () {
    ($this->log)('card.stamped');

    $this->actingAs($this->owner)
        ->get(route('dashboard.audit.index', ['category' => 'nope']))
        ->assertOk()
        ->assertSee('Ștampilă adăugată');
});

it('paginates the newest first', function () {
    foreach (range(1, 51) as $i) {
        ($this->log)('card.stamped');
    }
    ($this->log)('program.created');

    $this->actingAs($this->owner)->get(route('dashboard.audit.index'))->assertSee('Program creat');
    $this->actingAs($this->owner)->get(route('dashboard.audit.index', ['page' => 2]))->assertDontSee('Program creat');
});

it('has a label and a category for every action the app records', function () {
    $recorded = collect(glob(app_path('Services/Loyalty/*.php')))
        ->flatMap(function (string $file) {
            preg_match_all("/'((?:card|reward|transaction|stamp_request|program|member|location|business)\.[a-z_]+)'/", (string) file_get_contents($file), $matches);

            return $matches[1];
        })
        ->reject(fn (string $action) => in_array($action, ['card.customer', 'card.program'], true))
        ->unique();

    expect($recorded)->not->toBeEmpty();

    foreach ($recorded as $action) {
        expect(AuditActions::label($action))->not->toBe($action, "Lipsește eticheta pentru {$action}")
            ->and(AuditActions::category($action))->not->toBeNull();
    }
});

it('falls back to the raw action name for unknown actions', function () {
    expect(AuditActions::label('foo.bar'))->toBe('foo.bar');
});
