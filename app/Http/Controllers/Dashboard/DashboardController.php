<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Loyalty\ReportService;
use App\Support\Tenancy\CurrentBusiness;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, CurrentBusiness $currentBusiness, ReportService $reports): View
    {
        $business = $currentBusiness->get();
        abort_if($business === null, 403);

        /** @var User $user */
        $user = $request->user();
        $membership = $user->membershipIn($business);
        $role = $membership?->role;

        return view('dashboard.home', [
            'business' => $business,
            'role' => $role,
            // Cifrele rapide din „Rapoarte” (ADR 014), doar pentru manager+.
            'headline' => $membership !== null && $role?->atLeast(Role::Manager) ? $reports->headline($business, $membership) : null,
            'headlinePeriod' => ReportService::DEFAULT_PERIOD,
            'locationsCount' => $business->locations()->count(),
            'membersCount' => $business->members()->count(),
            'pendingInvitationsCount' => $business->invitations()
                ->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now())->count(),
        ]);
    }
}
