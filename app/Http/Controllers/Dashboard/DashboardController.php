<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Tenancy\CurrentBusiness;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, CurrentBusiness $currentBusiness): View
    {
        $business = $currentBusiness->get();
        abort_if($business === null, 403);

        /** @var User $user */
        $user = $request->user();

        return view('dashboard.home', [
            'business' => $business,
            'role' => $user->roleIn($business),
            'locationsCount' => $business->locations()->count(),
            'membersCount' => $business->members()->count(),
            'pendingInvitationsCount' => $business->invitations()
                ->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now())->count(),
        ]);
    }
}
