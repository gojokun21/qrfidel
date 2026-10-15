<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\StoreInvitationRequest;
use App\Models\Invitation;
use App\Models\Location;
use App\Models\User;
use App\Services\Loyalty\InvitationService;
use App\Support\Tenancy\CurrentBusiness;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class InvitationController extends Controller
{
    public function __construct(private InvitationService $invitations) {}

    public function store(StoreInvitationRequest $request, CurrentBusiness $currentBusiness): RedirectResponse
    {
        $business = $currentBusiness->get() ?? abort(403);
        Gate::authorize('create', [Invitation::class, $business]);

        /** @var User $user */
        $user = $request->user();
        $location = $request->filled('location_id') ? Location::query()->findOrFail($request->integer('location_id')) : null;

        $invitation = $this->invitations->invite(
            $user,
            $business,
            $request->string('email')->value(),
            Role::from($request->string('role')->value()),
            $location,
        );

        return redirect()->route('dashboard.team.index')->with('status', "Invitația a fost trimisă la {$invitation->email}.");
    }

    public function destroy(Request $request, Invitation $invitation): RedirectResponse
    {
        Gate::authorize('delete', $invitation);

        /** @var User $user */
        $user = $request->user();
        $this->invitations->revoke($user, $invitation);

        return redirect()->route('dashboard.team.index')->with('status', 'Invitația a fost anulată.');
    }
}
