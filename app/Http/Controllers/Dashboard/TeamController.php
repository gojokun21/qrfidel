<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\UpdateMemberRequest;
use App\Models\Business;
use App\Models\Location;
use App\Models\Membership;
use App\Models\User;
use App\Services\Loyalty\TeamService;
use App\Support\Tenancy\CurrentBusiness;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function __construct(private TeamService $team) {}

    public function index(CurrentBusiness $currentBusiness): View
    {
        $business = $this->current($currentBusiness);
        Gate::authorize('manageTeam', $business);

        return view('dashboard.team.index', [
            'business' => $business,
            'memberships' => $business->memberships()->with(['user', 'location'])->orderBy('id')->get(),
            'invitations' => $business->invitations()
                ->whereNull('accepted_at')
                ->whereNull('revoked_at')
                ->with('location')
                ->latest('id')
                ->get(),
            'locations' => $business->locations()->orderBy('name')->get(),
            'roles' => Role::cases(),
        ]);
    }

    public function update(UpdateMemberRequest $request, CurrentBusiness $currentBusiness, int $membership): RedirectResponse
    {
        $model = $this->membership($currentBusiness, $membership);
        Gate::authorize('update', $model);

        $location = $request->filled('location_id') ? Location::query()->findOrFail($request->integer('location_id')) : null;
        $updated = $this->team->changeRole($this->actor($request), $model, Role::from($request->string('role')->value()), $location);

        // Un owner care s-a retrogradat singur nu mai are acces la pagina echipei.
        if ($updated->user_id === $this->actor($request)->id && $updated->role !== Role::Owner) {
            return redirect()->route('dashboard')->with('status', 'Rolul tău a fost schimbat.');
        }

        return redirect()->route('dashboard.team.index')->with('status', 'Membrul a fost actualizat.');
    }

    public function destroy(Request $request, CurrentBusiness $currentBusiness, int $membership): RedirectResponse
    {
        $model = $this->membership($currentBusiness, $membership);
        Gate::authorize('delete', $model);

        $this->team->remove($this->actor($request), $model);

        // Cine s-a scos singur din echipă nu mai are acces la pagina echipei.
        if ($model->user_id === $this->actor($request)->id) {
            return redirect()->route('dashboard');
        }

        return redirect()->route('dashboard.team.index')->with('status', 'Membrul a fost scos din echipă.');
    }

    /**
     * Membership-ul nu are global scope (e pivot), deci îl căutăm explicit în afacerea curentă: altfel 404.
     */
    private function membership(CurrentBusiness $currentBusiness, int $id): Membership
    {
        return $this->current($currentBusiness)->memberships()->findOrFail($id);
    }

    private function current(CurrentBusiness $currentBusiness): Business
    {
        return $currentBusiness->get() ?? abort(403);
    }

    private function actor(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
