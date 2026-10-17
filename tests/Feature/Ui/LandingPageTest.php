<?php

use App\Models\User;

it('renders the landing page for guests', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('LoyUp')
        ->assertSee('id="rezultate"', false)
        ->assertSee('id="cum-functioneaza"', false)
        ->assertSee('id="pentru-cine"', false)
        ->assertSee('id="anti-frauda"', false)
        ->assertSee('id="roluri"', false)
        ->assertSee('Începe gratuit')
        ->assertSee(route('login'))
        ->assertSee(route('register'))
        ->assertSee('href="'.route('customer.login').'"', false);
});

it('has a single h1 with the headline', function () {
    $html = $this->get(route('home'))->assertOk()->getContent();

    expect(substr_count($html, '<h1'))->toBe(1)
        ->and($html)->toContain('Clienții revin.');
});

it('shows the program examples from the business templates', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSeeInOrder(['Cafenele', 'Restaurante', 'Spălătorii auto', 'Frizerii']);
});

it('no longer uses the old palette images or the script font', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('images/landing/', false)
        ->assertDontSee('kaushan', false);
});

it('uses the light logo on the light marketing pages', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('images/brand/loyup.png', false);
});

it('links logged-in users to the dashboard instead of the sign-up', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('home'))
        ->assertOk()
        ->assertSee(route('dashboard'))
        ->assertDontSee('Începe gratuit')
        ->assertDontSee(route('register'));
});
