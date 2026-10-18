<?php

use App\Enums\Role;
use App\Models\Business;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

test('the app speaks Romanian by default', function () {
    expect(app()->getLocale())->toBe('ro');
});

test('auth screens are translated', function () {
    $this->get('/register')
        ->assertSee('Ai deja cont?')
        ->assertSee('Creează cont')
        ->assertSee('Confirmă parola')
        ->assertDontSee('Already registered?');

    $this->get('/login')
        ->assertSee('Ține-mă minte')
        ->assertSee('Ai uitat parola?');
});

test('validation and login errors are translated', function () {
    $this->post('/register', [])->assertSessionHasErrors(['email' => 'Câmpul email este obligatoriu.']);

    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'gresit'])
        ->assertSessionHasErrors(['email' => 'Emailul sau parola nu sunt corecte.']);
});

test('the password reset email is translated', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $mail = $notification->toMail($user);

        return $mail->subject === 'Resetarea parolei' && $mail->actionText === 'Resetează parola';
    });
});

test('the verify email screen is translated', function () {
    $this->actingAs(User::factory()->unverified()->create())
        ->get('/verify-email')
        ->assertSee('Mulțumim că te-ai înregistrat!')
        ->assertDontSee('Thanks for signing up');
});

test('program validation names every field in Romanian', function () {
    $business = Business::factory()->create();
    $owner = User::factory()->withRole($business, Role::Owner)->create();

    $this->actingAs($owner)
        ->post(route('dashboard.programs.store'), ['rollover_extra' => 'poate'])
        ->assertSessionHasErrors(['rollover_extra' => 'Câmpul păstrarea ștampilelor în plus trebuie să fie adevărat sau fals.']);
});
