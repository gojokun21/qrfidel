<?php

use App\Enums\Role;
use App\Mail\CustomerMagicLink;
use App\Models\Business;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

it('has a session guard for customers backed by the customers table', function () {
    expect(config('auth.guards.customer'))->toBe(['driver' => 'session', 'provider' => 'customers'])
        ->and(config('auth.providers.customers.model'))->toBe(Customer::class)
        ->and(config('auth.defaults.guard'))->toBe('web');
});

it('sends guests from /me to the customer login', function () {
    $this->get(route('customer.cards'))->assertRedirect(route('customer.login'));
});

it('does not leave /me as the intended URL for the staff login', function () {
    $this->get(route('customer.cards'))->assertRedirect(route('customer.login'));

    expect(session()->has('url.intended'))->toBeFalse();

    $business = Business::factory()->create();
    $owner = User::factory()->withRole($business, Role::Owner)->create();

    $this->post(route('login'), ['email' => $owner->email, 'password' => 'password'])
        ->assertRedirect(route('dashboard', absolute: false));
});

it('sends staff guests to the staff login, not the customer one', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('does not treat a staff user as a customer', function () {
    $business = Business::factory()->create();
    $owner = User::factory()->withRole($business, Role::Owner)->create();

    $this->actingAs($owner)->get(route('customer.cards'))->assertRedirect(route('customer.login'));
});

it('does not let a customer into the dashboard', function () {
    $customer = Customer::factory()->create();

    $this->actingAs($customer, 'customer')->get(route('dashboard'))->assertRedirect(route('login'));
});

it('sends a signed-in customer from the login page to /me', function () {
    $customer = Customer::factory()->create();

    $this->actingAs($customer, 'customer')->get(route('customer.login'))->assertRedirect(route('customer.cards'));
});

it('shows the cards page with an empty state', function () {
    $customer = Customer::factory()->create(['email' => 'ana@client.test']);

    $this->actingAs($customer, 'customer')
        ->get(route('customer.cards'))
        ->assertOk()
        ->assertSee('ana@client.test')
        ->assertSee('Nu ai încă niciun card');
});

it('logs the customer out without ending the staff session in the same browser', function () {
    Mail::fake();
    $business = Business::factory()->create();
    $owner = User::factory()->withRole($business, Role::Owner)->create();

    // Sesiune reală: staff logat prin formular, apoi client logat prin magic link.
    $this->post(route('login'), ['email' => $owner->email, 'password' => 'password'])->assertSessionHasNoErrors();
    $this->post(route('customer.magic.send'), ['email' => 'ana@client.test']);
    Mail::assertSent(CustomerMagicLink::class, function (CustomerMagicLink $mail) {
        $this->post($mail->url);

        return true;
    });
    $this->assertAuthenticated('customer');

    $this->post(route('customer.logout'))->assertRedirect(route('customer.login'));

    $this->assertGuest('customer');
    expect(session()->has(Auth::guard('customer')->getName()))->toBeFalse()
        ->and(session()->get(Auth::guard('web')->getName()))->toBe($owner->id);
});

it('requires a signed-in customer to log out', function () {
    $this->post(route('customer.logout'))->assertRedirect(route('customer.login'));
});
