<?php

use App\Enums\Role;
use App\Http\Middleware\EnsureBusinessMember;
use App\Models\Business;
use App\Models\User;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->business = Business::factory()->create(['name' => 'Cafeneaua Centrală']);
});

/**
 * Doar HTML-ul sidebar-ului, ca linkurile din conținutul paginii să nu influențeze aserțiunile.
 */
function sidebarOf(TestResponse $response): string
{
    $response->assertOk();
    preg_match('/<aside[^>]*data-sidebar[^>]*>.*?<\/aside>/s', (string) $response->getContent(), $matches);

    expect($matches)->not->toBeEmpty('Pagina nu are sidebar.');

    return $matches[0];
}

it('shows the links allowed for each role', function (Role $role, array $visible, array $hidden) {
    $user = User::factory()->withRole($this->business, $role)->create();

    $sidebar = sidebarOf($this->actingAs($user)->get(route('dashboard')));

    foreach ($visible as $routeName) {
        expect($sidebar)->toContain('href="'.route($routeName).'"');
    }
    foreach ($hidden as $routeName) {
        expect($sidebar)->not->toContain('href="'.route($routeName).'"');
    }
})->with([
    'owner' => [Role::Owner, ['dashboard', 'staff.scan', 'staff.requests', 'dashboard.programs.index', 'dashboard.locations.index', 'dashboard.team.index', 'dashboard.business.edit'], []],
    'manager' => [Role::Manager, ['dashboard', 'staff.scan', 'staff.requests', 'dashboard.programs.index', 'dashboard.locations.index'], ['dashboard.team.index', 'dashboard.business.edit']],
    'staff' => [Role::Staff, ['dashboard', 'staff.scan', 'staff.requests'], ['dashboard.programs.index', 'dashboard.locations.index', 'dashboard.team.index', 'dashboard.business.edit']],
]);

it('marks the current page link as active', function () {
    $owner = User::factory()->withRole($this->business, Role::Owner)->create();

    $sidebar = sidebarOf($this->actingAs($owner)->get(route('dashboard.team.index')));

    expect($sidebar)->toMatch('/<a[^>]*href="'.preg_quote(route('dashboard.team.index'), '/').'"[^>]*aria-current="page"/')
        ->and(substr_count($sidebar, 'aria-current="page"'))->toBe(1);
});

it('lists only the businesses the user belongs to in the switcher', function () {
    $other = Business::factory()->create(['name' => 'Spălătoria Mea']);
    Business::factory()->create(['name' => 'Afacere Străină']);
    $owner = User::factory()->withRole($this->business, Role::Owner)->withRole($other, Role::Staff)->create();

    $sidebar = sidebarOf(
        $this->actingAs($owner)
            ->withSession([EnsureBusinessMember::SESSION_KEY => $this->business->id])
            ->get(route('dashboard'))
    );

    expect($sidebar)->toContain('Cafeneaua Centrală')
        ->toContain('Spălătoria Mea')
        ->toContain('name="business_id" value="'.$other->id.'"')
        ->not->toContain('value="'.$this->business->id.'"')
        ->not->toContain('Afacere Străină');
});

it('does not offer switching to a suspended business', function () {
    $suspended = Business::factory()->suspended()->create(['name' => 'Afacere Suspendată']);
    $owner = User::factory()->withRole($this->business, Role::Owner)->withRole($suspended, Role::Owner)->create();

    $sidebar = sidebarOf($this->actingAs($owner)->get(route('dashboard')));

    expect($sidebar)->not->toContain('Afacere Suspendată')
        ->not->toContain('value="'.$suspended->id.'"');
});

it('keeps the business navigation on pages outside the business context', function () {
    $owner = User::factory()->withRole($this->business, Role::Owner)->create();

    $sidebar = sidebarOf($this->actingAs($owner)->get(route('profile.edit')));

    expect($sidebar)->toContain('Cafeneaua Centrală')
        ->toContain('href="'.route('dashboard.team.index').'"')
        ->toContain('href="'.route('profile.edit').'"');
});

it('does not offer the links of a suspended business outside its context', function () {
    $suspended = Business::factory()->suspended()->create();
    $owner = User::factory()->withRole($suspended, Role::Owner)->create();

    $sidebar = sidebarOf($this->actingAs($owner)->get(route('profile.edit')));

    expect($sidebar)->not->toContain('href="'.route('dashboard.team.index').'"')
        ->toContain('href="'.route('dashboard.business.create').'"');
});
