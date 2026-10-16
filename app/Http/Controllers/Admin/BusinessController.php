<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BusinessStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ChangeBusinessStatusRequest;
use App\Models\Business;
use App\Models\User;
use App\Services\Loyalty\BusinessService;
use App\Services\Loyalty\PlatformOverview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Panoul adminului de platformă (SDD §3, ADR 014): toate afacerile, detaliul uneia și suspendarea.
 * Adminul vede cifrele, dar nu are puteri de staff în afaceri.
 */
class BusinessController extends Controller
{
    public function index(Request $request, PlatformOverview $overview): View
    {
        Gate::authorize('viewAny', Business::class);

        $search = $request->string('q')->toString();
        $status = BusinessStatus::tryFrom($request->string('status')->toString());

        return view('admin.index', [
            'totals' => $overview->totals(),
            'businesses' => $overview->businesses($search, $status),
            'statuses' => BusinessStatus::cases(),
            'filters' => ['q' => trim($search), 'status' => $status],
        ]);
    }

    public function show(Business $business, PlatformOverview $overview): View
    {
        Gate::authorize('view', $business);

        return view('admin.show', ['business' => $business, ...$overview->details($business)]);
    }

    public function suspend(ChangeBusinessStatusRequest $request, Business $business, BusinessService $businesses): RedirectResponse
    {
        $changed = $businesses->suspend($this->admin($request), $business, $request->reason());

        return back()->with('status', $changed ? 'Afacerea a fost suspendată.' : 'Afacerea era deja suspendată.');
    }

    public function reactivate(ChangeBusinessStatusRequest $request, Business $business, BusinessService $businesses): RedirectResponse
    {
        $changed = $businesses->reactivate($this->admin($request), $business, $request->reason());

        return back()->with('status', $changed ? 'Afacerea a fost reactivată.' : 'Afacerea era deja activă.');
    }

    private function admin(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
