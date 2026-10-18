<?php

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

beforeEach(function () {
    $this->business = Business::factory()->create();
    $this->owner = User::factory()->withRole($this->business, Role::Owner)->create();
});

it('links an invalid field to its error message for screen readers', function () {
    $this->actingAs($this->owner)
        ->from(route('dashboard.locations.create'))
        ->followingRedirects()
        ->post(route('dashboard.locations.store'), ['name' => ''])
        ->assertOk()
        ->assertSee('id="name"', false)
        ->assertSee('aria-invalid="true"', false)
        ->assertSee('aria-describedby="name-error"', false)
        ->assertSee('id="name-error"', false);
});

it('marks only the field that failed, not same-named controls in other forms', function () {
    $this->actingAs($this->owner)
        ->withSession(['errors' => (new ViewErrorBag)->put('default', new MessageBag(['location_id' => 'Locația nu există.']))])
        ->get(route('dashboard.team.index'))
        ->assertOk()
        ->assertSee('Locația nu există.')
        ->assertSee('aria-describedby="invite_location-error"', false)
        ->tap(fn ($response) => expect(substr_count((string) $response->getContent(), 'aria-invalid="true"'))->toBe(1));
});

it('describes a field by its hint', function () {
    $this->blade('<x-ui.field label="Fusul orar" for="timezone" hint="Indiciu"><x-ui.input id="timezone" name="timezone" /></x-ui.field>')
        ->assertSee('aria-describedby="timezone-hint"', false)
        ->assertSee('id="timezone-hint"', false);
});

it('leaves valid fields unmarked', function () {
    $this->actingAs($this->owner)
        ->get(route('dashboard.business.edit'))
        ->assertOk()
        ->assertDontSee('aria-invalid', false);
});

it('shows business messages in the layout but not the Breeze status codes', function () {
    $this->actingAs($this->owner)
        ->withSession(['status' => 'Locația a fost salvată.'])
        ->get(route('dashboard.locations.index'))
        ->assertSee('Locația a fost salvată.');

    $this->actingAs($this->owner)
        ->withSession(['status' => 'profile-updated'])
        ->get(route('profile.edit'))
        ->assertDontSee('>profile-updated<', false);
});

it('renders the pagination in Romanian with the current page marked', function () {
    AuditLog::factory()->count(51)->create(['business_id' => $this->business->id, 'action' => 'location.created', 'payload' => null]);

    $this->actingAs($this->owner)
        ->get(route('dashboard.audit.index', ['page' => 2]))
        ->assertOk()
        ->assertSee('aria-label="Paginare"', false)
        ->assertSeeInOrder(['← Înapoi', 'aria-current="page">2</span>', 'Înainte →'], false);
});

it('uses the brand font everywhere, never a monospace one', function () {
    $offenders = collect(File::allFiles(resource_path('views')))
        ->filter(fn ($file) => str_contains($file->getContents(), 'font-mono'))
        ->map(fn ($file) => $file->getRelativePathname())
        ->values()
        ->all();

    expect($offenders)->toBe([]);
});
