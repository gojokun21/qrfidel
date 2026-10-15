<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureBusinessMember;
use App\Http\Requests\Invitations\RegisterFromInvitationRequest;
use App\Models\Business;
use App\Models\User;
use App\Services\Loyalty\InvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Pagina publică din linkul invitației. O invitație nefolosibilă ajunge la pagina `invitations.invalid`
 * (InvitationInvalid e randată în bootstrap/app.php).
 */
class InvitationAcceptController extends Controller
{
    public function __construct(private InvitationService $invitations) {}

    public function show(Request $request, string $token): View
    {
        $invitation = $this->invitations->pendingByToken($token);
        $user = $request->user();

        if ($user === null) {
            // După login, userul revine aici ca să accepte.
            $request->session()->put('url.intended', $request->fullUrl());
        }

        return view('invitations.show', [
            'token' => $token,
            'invitation' => $invitation,
            'business' => Business::query()->findOrFail($invitation->business_id),
            'user' => $user,
            'emailMatches' => $user instanceof User && Str::lower($user->email) === Str::lower($invitation->email),
            'accountExists' => User::query()->whereRaw('lower(email) = ?', [Str::lower($invitation->email)])->exists(),
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $membership = $this->invitations->accept($token, $user);

        $request->session()->put(EnsureBusinessMember::SESSION_KEY, $membership->business_id);

        return redirect()->route('dashboard')->with('status', 'Bine ai venit în echipă!');
    }

    public function register(RegisterFromInvitationRequest $request, string $token): RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('invitations.show', $token);
        }

        $membership = $this->invitations->acceptAsNewUser(
            $token,
            $request->string('name')->value(),
            $request->string('password')->value(),
        );

        /** @var User $user */
        $user = $membership->user;
        Auth::login($user);
        $request->session()->regenerate();

        $request->session()->put(EnsureBusinessMember::SESSION_KEY, $membership->business_id);

        return redirect()->route('dashboard')->with('status', 'Bine ai venit în echipă!');
    }
}
