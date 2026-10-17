<?php

use App\Models\User;

it('renders the landing page for guests', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('LoyUp')
        ->assertSee('id="cum-functioneaza"', false)
        ->assertSee('id="pentru-cine"', false)
        ->assertSee('id="anti-frauda"', false)
        ->assertSee('Începe gratuit')
        ->assertSee(route('login'))
        ->assertSee(route('register'))
        ->assertSee('href="'.route('customer.login').'"', false);
});

it('shows the program examples from the business templates', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSeeInOrder(['Cafenele', 'Restaurante', 'Spălătorii auto', 'Frizerii']);
});

it('links logged-in users to the dashboard instead of the sign-up', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('home'))
        ->assertOk()
        ->assertSee(route('dashboard'))
        ->assertDontSee('Începe gratuit')
        ->assertDontSee(route('register'));
});
