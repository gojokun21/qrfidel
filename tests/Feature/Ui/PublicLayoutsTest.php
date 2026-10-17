<?php

use App\Models\Business;
use App\Models\User;

it('renders the staff login on the light brand layout with a way back to the site', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('images/brand/loyup.png', false)
        ->assertSee('href="'.route('home').'"', false)
        ->assertSee('Înapoi la site')
        ->assertSee('Vezi cardurile tale');
});

it('keeps the customer link off the staff pages for signed-in users', function () {
    $this->actingAs(User::factory()->unverified()->create())
        ->get(route('verification.notice'))
        ->assertOk()
        ->assertDontSee('Vezi cardurile tale');
});

it('uses a light status bar for the customer PWA', function () {
    $this->get(route('customer.login'))
        ->assertOk()
        ->assertSee('<meta name="theme-color" content="#FFFFFF">', false)
        ->assertSee('<meta name="apple-mobile-web-app-status-bar-style" content="default">', false);
});

it('renders the customer login with the light header', function () {
    $this->get(route('customer.login'))
        ->assertOk()
        ->assertSee('images/brand/loyup.png', false);
});

it('renders the public business page with its name as the only h1', function () {
    $business = Business::factory()->create(['name' => 'Cafeneaua Verde']);

    $html = $this->get(route('customer.business', $business))->assertOk()->getContent();

    expect(substr_count($html, '<h1'))->toBe(1)
        ->and($html)->toContain('Cafeneaua Verde');
});
