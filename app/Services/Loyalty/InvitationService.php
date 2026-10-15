<?php

namespace App\Services\Loyalty;

use App\Enums\Role;
use App\Exceptions\InvitationInvalid;
use App\Exceptions\TeamRuleViolation;
use App\Mail\TeamInvitation;
use App\Models\Business;
use App\Models\Invitation;
use App\Models\Location;
use App\Models\Membership;
use App\Models\User;
use App\Services\Loyalty\Concerns\RequiresOwner;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Invitațiile în echipă (ADR 006): link cu token aleator (în DB doar hash-ul), valabil 7 zile, o singură
 * invitație activă per email. Acceptarea creează membership-ul și marchează emailul ca verificat
 * (linkul a ajuns în căsuța lui).
 */
class InvitationService
{
    use RequiresOwner;

    public const VALID_DAYS = 7;

    public function __construct(private AuditTrail $audit) {}

    public function invite(User $actor, Business $business, string $email, Role $role, ?Location $location): Invitation
    {
        $this->authorizeOwner($actor, $business);

        $email = Str::lower(trim($email));

        if ($business->members()->whereRaw('lower(users.email) = ?', [$email])->exists()) {
            throw new TeamRuleViolation('Această persoană face deja parte din echipă.');
        }

        $token = Str::random(40);

        try {
            $invitation = $this->createInvitation($actor, $business, $email, $role, $location, $token);
        } catch (UniqueConstraintViolationException) {
            // Două trimiteri simultane (dublu-click): a doua nu vede rândul primei și lovește indexul unic parțial.
            throw new TeamRuleViolation("O invitație pentru {$email} tocmai a fost trimisă.");
        }

        Mail::to($email)->send(new TeamInvitation($invitation, $business, $token));

        return $invitation;
    }

    private function createInvitation(User $actor, Business $business, string $email, Role $role, ?Location $location, string $token): Invitation
    {
        return DB::transaction(function () use ($actor, $business, $email, $role, $location, $token): Invitation {
            // Re-invitarea înlocuiește invitația activă (indexul unic parțial permite una singură).
            Invitation::query()
                ->where('business_id', $business->id)
                ->whereRaw('lower(email) = ?', [$email])
                ->whereNull('accepted_at')
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            $invitation = Invitation::query()->create([
                'business_id' => $business->id,
                'email' => $email,
                'role' => $role,
                'location_id' => $role === Role::Owner ? null : $location?->id,
                'token_hash' => Invitation::hashToken($token),
                'invited_by' => $actor->id,
                'expires_at' => now()->addDays(self::VALID_DAYS),
            ]);

            $this->audit->recordForBusiness($actor, $business, 'member.invited', $invitation, [
                'email' => $email,
                'role' => $role->value,
                'location_id' => $invitation->location_id,
            ]);

            return $invitation;
        });
    }

    public function revoke(User $actor, Invitation $invitation): void
    {
        $this->authorizeOwner($actor, $invitation->business_id);

        DB::transaction(function () use ($actor, $invitation): void {
            $invitation = Invitation::query()->withoutGlobalScopes()->whereKey($invitation->id)->lockForUpdate()->firstOrFail();

            if ($invitation->isAccepted() || $invitation->isRevoked()) {
                throw new TeamRuleViolation('Invitația nu mai e activă.');
            }

            $invitation->revoked_at = now();
            $invitation->save();

            $this->audit->recordForBusiness($actor, $invitation->business_id, 'member.invitation_revoked', $invitation, [
                'email' => $invitation->email,
            ]);
        });
    }

    /**
     * Invitația pentru tokenul din link, oricare i-ar fi starea; null dacă tokenul nu există.
     */
    public function findByToken(string $token): ?Invitation
    {
        return Invitation::query()->withoutGlobalScopes()->where('token_hash', Invitation::hashToken($token))->first();
    }

    /**
     * Invitația valabilă pentru token sau excepția cu motivul (inexistentă, folosită, anulată, expirată).
     */
    public function pendingByToken(string $token): Invitation
    {
        $invitation = $this->findByToken($token) ?? throw InvitationInvalid::notFound();

        if (! $invitation->isPending()) {
            throw InvitationInvalid::unusable($invitation);
        }

        return $invitation;
    }

    /**
     * Un user existent (logat cu emailul invitat) acceptă invitația.
     */
    public function accept(string $token, User $user): Membership
    {
        return DB::transaction(function () use ($token, $user): Membership {
            $invitation = $this->lockPending($token);

            if (Str::lower($user->email) !== Str::lower($invitation->email)) {
                throw InvitationInvalid::wrongAccount($invitation);
            }

            return $this->join($invitation, $user);
        });
    }

    /**
     * Cineva fără cont acceptă invitația: contul se creează cu emailul invitat, deja verificat.
     */
    public function acceptAsNewUser(string $token, string $name, string $password): Membership
    {
        return DB::transaction(function () use ($token, $name, $password): Membership {
            $invitation = $this->lockPending($token);

            if (User::query()->whereRaw('lower(email) = ?', [Str::lower($invitation->email)])->exists()) {
                throw new TeamRuleViolation('Există deja un cont cu acest email. Autentifică-te ca să accepți invitația.');
            }

            $user = User::query()->create([
                'name' => $name,
                'email' => Str::lower($invitation->email),
                'password' => Hash::make($password),
            ]);

            return $this->join($invitation, $user)->setRelation('user', $user);
        });
    }

    /**
     * Lock pe invitație: două acceptări simultane (dublu-click) se serializează, a doua vede `accepted_at`.
     */
    private function lockPending(string $token): Invitation
    {
        $invitation = Invitation::query()
            ->withoutGlobalScopes()
            ->where('token_hash', Invitation::hashToken($token))
            ->lockForUpdate()
            ->first() ?? throw InvitationInvalid::notFound();

        if (! $invitation->isPending()) {
            throw InvitationInvalid::unusable($invitation);
        }

        return $invitation;
    }

    private function join(Invitation $invitation, User $user): Membership
    {
        $business = Business::query()->findOrFail($invitation->business_id);

        if ($user->isMemberOf($business)) {
            throw InvitationInvalid::alreadyMember();
        }

        $business->members()->attach($user, [
            'role' => $invitation->role,
            'location_id' => $invitation->location_id,
        ]);

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        $invitation->accepted_at = now();
        $invitation->accepted_by = $user->id;
        $invitation->save();

        $this->audit->recordForBusiness($user, $business, 'member.invitation_accepted', $invitation, ['role' => $invitation->role->value]);

        return $user->membershipIn($business) ?? throw new \LogicException('Membership missing after joining.');
    }
}
