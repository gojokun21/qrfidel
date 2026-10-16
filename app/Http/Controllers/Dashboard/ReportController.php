<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\User;
use App\Services\Loyalty\ReportService;
use App\Support\Tenancy\CurrentBusiness;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * „Rapoarte” (manager+, SDD §9, ADR 014). O perioadă necunoscută revine la cea implicită, ca filtrul din „Jurnal”.
 */
class ReportController extends Controller
{
    public function __invoke(Request $request, CurrentBusiness $currentBusiness, ReportService $reports): View
    {
        $business = $currentBusiness->get() ?? abort(403);

        /** @var User $user */
        $user = $request->user();
        $membership = $user->membershipIn($business) ?? abort(403);

        $report = $reports->forMember($business, $membership, ReportService::period($request->query('zile')));

        return view('dashboard.reports.index', [
            'business' => $business,
            'report' => $report,
            'periods' => ReportService::PERIODS,
            'restrictedTo' => $report->locationId !== null ? Location::query()->find($report->locationId) : null,
        ]);
    }
}
