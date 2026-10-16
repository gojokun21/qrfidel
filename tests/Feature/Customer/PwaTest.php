<?php

use App\Enums\Role;
use App\Models\Business;
use App\Models\Customer;
use App\Models\User;

it('has a valid web app manifest that opens on the customer cards', function () {
    $manifest = json_decode((string) file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

    expect($manifest)->toMatchArray([
        'start_url' => '/me',
        'scope' => '/',
        'display' => 'standalone',
        'lang' => 'ro',
    ])->and($manifest['name'])->not->toBeEmpty()
        ->and($manifest['short_name'])->not->toBeEmpty()
        ->and($manifest['theme_color'])->toMatch('/^#[0-9A-Fa-f]{6}$/')
        ->and($manifest['background_color'])->toMatch('/^#[0-9A-Fa-f]{6}$/');

    $icons = collect($manifest['icons']);

    expect($icons->pluck('sizes')->all())->toContain('192x192', '512x512')
        ->and($icons->where('purpose', 'maskable')->isNotEmpty())->toBeTrue();

    foreach ($icons as $icon) {
        $path = public_path(ltrim($icon['src'], '/'));
        expect($path)->toBeFile();

        [$width, $height] = getimagesize($path) ?: [0, 0];
        expect("{$width}x{$height}")->toBe($icon['sizes']);
    }
});

it('has a minimal service worker with an offline page and no cached private pages', function () {
    expect(public_path('sw.js'))->toBeFile()
        ->and(public_path('offline.html'))->toBeFile()
        ->and(public_path('icons/apple-touch-icon.png'))->toBeFile();

    $sw = (string) file_get_contents(public_path('sw.js'));

    expect($sw)->toContain("'/offline.html'")
        ->and($sw)->toContain('/build/assets/')
        ->and($sw)->not->toContain("'/me");
});

it('links the manifest and registers the service worker on customer pages', function () {
    $this->get(route('customer.login'))->assertOk()->assertSee('rel="manifest"', false);

    $this->actingAs(Customer::factory()->create(), 'customer')
        ->get(route('customer.cards'))
        ->assertOk()
        ->assertSee('<link rel="manifest" href="/manifest.webmanifest">', false)
        ->assertSee('rel="apple-touch-icon"', false)
        ->assertSee('data-pwa', false)
        ->assertSee('installPrompt', false);
});

it('keeps the PWA out of the staff dashboard', function () {
    $business = Business::factory()->create();
    $owner = User::factory()->withRole($business, Role::Owner)->create();

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('rel="manifest"', false)
        ->assertDontSee('data-pwa', false);
});
