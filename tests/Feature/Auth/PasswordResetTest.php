<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

test('reset password link screen can be rendered', function () {
    $response = $this->get('/forgot-password');

    $response->assertStatus(200);
});

test('reset password link can be requested', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class);
});

test('reset password screen can be rendered', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
        $response = $this->get('/reset-password/'.$notification->token);

        $response->assertStatus(200);

        return true;
    });
});

test('password can be reset with valid token', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $response = $this->post('/reset-password', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        return true;
    });
});

test('the reset link request answers the same for known and unknown emails', function () {
    Notification::fake();
    $user = User::factory()->create();

    $known = $this->post('/forgot-password', ['email' => $user->email]);
    $unknown = $this->post('/forgot-password', ['email' => 'nimeni@example.test']);
    $throttled = $this->post('/forgot-password', ['email' => $user->email]);

    foreach ([$known, $unknown, $throttled] as $response) {
        $response->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Dacă adresa are un cont, îți trimitem pe email linkul de resetare a parolei.');
    }

    Notification::assertSentToTimes($user, ResetPassword::class, 1);
});

test('the reset password screen has a heading', function () {
    $this->get('/reset-password/token?email=ana@example.test')
        ->assertOk()
        ->assertSee('Alege o parolă nouă');
});
