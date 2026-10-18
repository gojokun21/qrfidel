<?php

use App\Enums\Role;
use App\Mail\TeamInvitation;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Invitation;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

beforeEach(function () {
    Mail::fake();

    $this->business = Business::factory()->create(['name' => 'Cafenea Test']);
    $this->location = Location::factory()->for($this->business)->create();
    $this->owner = User::factory()->withRole($this->business, Role::Owner)->create();
});

/**
 * Trimite o invitație ca owner și întoarce tokenul în clar din emailul trimis.
 *
 * @param  array<string, mixed>  $data
 */
function inviteAndCaptureToken(object $test, array $data = []): string
{
    $test->actingAs($test->owner)
        ->post(route('dashboard.invitations.store'), [
            'email' => 'nou@exemplu.test',
            'role' => 'staff',
            ...$data,
        ])
        ->assertSessionHasNoErrors();

    $token = null;
    Mail::assertSent(TeamInvitation::class, function (TeamInvitation $mail) use (&$token) {
        $token = $mail->token;

        return true;
    });

    Auth::logout();

    return $token;
}

it('owner creates a location and invites an employee who joins the team', function () {
    $this->actingAs($this->owner)
        ->post(route('dashboard.locations.store'), ['idempotency_key' => (string) Str::uuid(), 'name' => 'Gară'])
        ->assertSessionHasNoErrors();
    $location = Location::query()->where('name', 'Gară')->sole();

    $token = inviteAndCaptureToken($this, ['location_id' => $location->id]);

    $this->get(route('invitations.show', $token))->assertOk()->assertSee('Cafenea Test');
    $this->post(route('invitations.register', $token), [
        'name' => 'Ion Barista',
        'password' => 'parola-sigura-123',
        'password_confirmation' => 'parola-sigura-123',
    ])->assertRedirect(route('dashboard'));

    $employee = User::query()->where('email', 'nou@exemplu.test')->sole();

    expect(Auth::id())->toBe($employee->id)
        ->and($employee->hasVerifiedEmail())->toBeTrue()
        ->and($employee->roleIn($this->business))->toBe(Role::Staff)
        ->and($employee->membershipIn($this->business)->location_id)->toBe($location->id);

    $this->actingAs($this->owner)->get(route('dashboard.team.index'))->assertSee('Ion Barista');
});

it('sends an invitation email with an accept link', function () {
    $this->actingAs($this->owner)
        ->post(route('dashboard.invitations.store'), ['email' => 'Nou@Exemplu.test', 'role' => 'manager'])
        ->assertRedirect(route('dashboard.team.index'));

    $invitation = Invitation::query()->sole();

    expect($invitation->email)->toBe('nou@exemplu.test')
        ->and($invitation->role)->toBe(Role::Manager)
        ->and($invitation->invited_by)->toBe($this->owner->id)
        ->and($invitation->expires_at->isFuture())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'member.invited')->exists())->toBeTrue();

    Mail::assertSent(TeamInvitation::class, function (TeamInvitation $mail) use ($invitation) {
        return $mail->hasTo('nou@exemplu.test')
            && $invitation->token_hash === hash('sha256', $mail->token)
            && str_contains($mail->render(), route('invitations.show', $mail->token));
    });
});

it('stores only a hash of the token', function () {
    $token = inviteAndCaptureToken($this);

    expect(Invitation::query()->sole()->token_hash)->toBe(hash('sha256', $token))
        ->and(Invitation::query()->where('token_hash', $token)->exists())->toBeFalse();
});

it('refuses to invite someone who is already a member', function () {
    User::factory()->withRole($this->business, Role::Staff)->create(['email' => 'membru@exemplu.test']);

    $this->actingAs($this->owner)
        ->post(route('dashboard.invitations.store'), ['email' => 'MEMBRU@exemplu.test', 'role' => 'staff'])
        ->assertSessionHasErrors('email');

    Mail::assertNothingSent();
});

it('validates the invitation', function (array $data, string $field) {
    $this->actingAs($this->owner)
        ->post(route('dashboard.invitations.store'), ['email' => 'nou@exemplu.test', 'role' => 'staff', ...$data])
        ->assertSessionHasErrors($field);
})->with([
    'bad email' => [['email' => 'nu-e-email'], 'email'],
    'bad role' => [['role' => 'admin'], 'role'],
    'foreign location' => [fn () => ['location_id' => Location::factory()->create()->id], 'location_id'],
]);

it('replaces a pending invitation when inviting the same email again', function () {
    $first = inviteAndCaptureToken($this);
    inviteAndCaptureToken($this, ['role' => 'manager']);

    expect(Invitation::query()->count())->toBe(2)
        ->and(Invitation::query()->whereNull('revoked_at')->sole()->role)->toBe(Role::Manager);

    $this->get(route('invitations.show', $first))->assertStatus(410)->assertSee('Invitația a fost anulată.');
});

it('forbids managers and staff from inviting', function (Role $role) {
    $user = User::factory()->withRole($this->business, $role)->create();

    $this->actingAs($user)
        ->post(route('dashboard.invitations.store'), ['email' => 'nou@exemplu.test', 'role' => 'staff'])
        ->assertForbidden();

    Mail::assertNothingSent();
})->with([Role::Manager, Role::Staff]);

it('lets the owner revoke a pending invitation', function () {
    $token = inviteAndCaptureToken($this);
    $invitation = Invitation::query()->sole();

    $this->actingAs($this->owner)
        ->delete(route('dashboard.invitations.destroy', $invitation))
        ->assertRedirect(route('dashboard.team.index'));

    expect($invitation->refresh()->revoked_at)->not->toBeNull()
        ->and(AuditLog::query()->where('action', 'member.invitation_revoked')->exists())->toBeTrue();

    Auth::logout();
    $this->post(route('invitations.register', $token), [
        'name' => 'X', 'password' => 'parola-sigura-123', 'password_confirmation' => 'parola-sigura-123',
    ]);

    expect(User::query()->where('email', 'nou@exemplu.test')->exists())->toBeFalse();
});

it('hides invitations of other businesses', function () {
    $foreign = Invitation::factory()->create();

    $this->actingAs($this->owner)->delete(route('dashboard.invitations.destroy', $foreign))->assertNotFound();

    expect($foreign->refresh()->revoked_at)->toBeNull();
});

it('lets an existing user accept while logged in with the invited email', function () {
    $existing = User::factory()->unverified()->create(['email' => 'nou@exemplu.test']);
    $token = inviteAndCaptureToken($this, ['role' => 'manager', 'location_id' => $this->location->id]);

    $this->get(route('invitations.show', $token))->assertOk()->assertSee(route('login'));

    $this->actingAs($existing)->get(route('invitations.show', $token))->assertOk();
    $this->actingAs($existing)
        ->post(route('invitations.accept', $token))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('current_business_id', $this->business->id);

    $invitation = Invitation::query()->sole();

    expect($existing->roleIn($this->business))->toBe(Role::Manager)
        ->and($existing->refresh()->hasVerifiedEmail())->toBeTrue()
        ->and($invitation->accepted_at)->not->toBeNull()
        ->and($invitation->accepted_by)->toBe($existing->id)
        ->and(AuditLog::query()->where('action', 'member.invitation_accepted')->exists())->toBeTrue();
});

it('refuses an acceptance from an account with another email', function () {
    $token = inviteAndCaptureToken($this);
    $other = User::factory()->create(['email' => 'altcineva@exemplu.test']);

    $this->actingAs($other)
        ->post(route('invitations.accept', $token))
        ->assertStatus(403)
        ->assertSee('nou@exemplu.test');

    expect($other->isMemberOf($this->business))->toBeFalse()
        ->and(Invitation::query()->sole()->accepted_at)->toBeNull();
});

it('refuses to register a new account for an email that already has one', function () {
    User::factory()->create(['email' => 'nou@exemplu.test']);
    $token = inviteAndCaptureToken($this);

    $this->post(route('invitations.register', $token), [
        'name' => 'Impostor', 'password' => 'parola-sigura-123', 'password_confirmation' => 'parola-sigura-123',
    ])->assertSessionHasErrors('loyalty');

    expect(Auth::check())->toBeFalse()
        ->and(User::query()->where('email', 'nou@exemplu.test')->count())->toBe(1);
});

it('validates the registration form', function () {
    $token = inviteAndCaptureToken($this);

    $this->post(route('invitations.register', $token), [
        'name' => '', 'password' => 'scurt', 'password_confirmation' => 'altceva',
    ])->assertSessionHasErrors(['name', 'password']);
});

it('rejects an unknown token', function () {
    $this->get(route('invitations.show', 'token-inexistent'))->assertNotFound();
    $this->post(route('invitations.register', 'token-inexistent'), [
        'name' => 'X', 'password' => 'parola-sigura-123', 'password_confirmation' => 'parola-sigura-123',
    ])->assertNotFound();
});

it('rejects expired, revoked and used invitations', function (string $state, string $message) {
    $invitation = Invitation::factory()->for($this->business)->withToken('token-de-test')->{$state}()->create();

    $this->get(route('invitations.show', 'token-de-test'))->assertStatus(410)->assertSee($message);
    $this->post(route('invitations.register', 'token-de-test'), [
        'name' => 'X', 'password' => 'parola-sigura-123', 'password_confirmation' => 'parola-sigura-123',
    ])->assertStatus(410);

    expect(User::query()->where('email', $invitation->email)->exists())->toBeFalse();
})->with([
    'expired' => ['expired', 'Invitația a expirat. Cere ownerului una nouă.'],
    'revoked' => ['revoked', 'Invitația a fost anulată.'],
    'accepted' => ['accepted', 'Invitația a fost deja folosită.'],
]);

it('accepts an invitation only once', function () {
    $token = inviteAndCaptureToken($this);
    $data = ['name' => 'Ion', 'password' => 'parola-sigura-123', 'password_confirmation' => 'parola-sigura-123'];

    $this->post(route('invitations.register', $token), $data)->assertRedirect(route('dashboard'));
    $user = User::query()->where('email', 'nou@exemplu.test')->sole();

    $this->actingAs($user)->post(route('invitations.accept', $token))->assertStatus(410);

    expect($this->business->members()->where('users.id', $user->id)->count())->toBe(1);
});

it('refuses an invitation for someone who joined in the meantime', function () {
    $token = inviteAndCaptureToken($this);
    $user = User::factory()->withRole($this->business, Role::Staff)->create(['email' => 'nou@exemplu.test']);

    $this->actingAs($user)
        ->post(route('invitations.accept', $token))
        ->assertStatus(409)
        ->assertSee('Faci deja parte din această afacere.');

    expect($user->roleIn($this->business))->toBe(Role::Staff);
});
