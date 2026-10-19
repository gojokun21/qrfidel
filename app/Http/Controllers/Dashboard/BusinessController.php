<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\BusinessType;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureBusinessMember;
use App\Http\Requests\Dashboard\StoreBusinessRequest;
use App\Http\Requests\Dashboard\SwitchBusinessRequest;
use App\Http\Requests\Dashboard\UpdateBusinessRequest;
use App\Models\Business;
use App\Models\User;
use App\Services\Loyalty\BusinessService;
use App\Services\Loyalty\IdempotentCreation;
use App\Support\Tenancy\CurrentBusiness;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class BusinessController extends Controller
{
    public function __construct(private BusinessService $businesses, private IdempotentCreation $idempotent) {}

    public function create(): View
    {
        return view('dashboard.business.create', [
            'types' => BusinessType::cases(),
            'timezones' => timezone_identifiers_list(),
        ]);
    }

    public function store(StoreBusinessRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var array{name: string, slug?: string|null, type: string, timezone: string, brand_color?: string|null} $data */
        $data = $request->validated();
        $businessId = $this->idempotent->once($user, 'business', $request->string('idempotency_key')->value(),
            fn () => $this->businesses->create($user, $data));

        $request->session()->put(EnsureBusinessMember::SESSION_KEY, $businessId);

        return redirect()->route('dashboard')->with('status', 'Afacerea a fost creată. Adaugă prima locație.');
    }

    public function edit(CurrentBusiness $currentBusiness): View
    {
        $business = $this->current($currentBusiness);
        Gate::authorize('update', $business);

        return view('dashboard.business.edit', [
            'business' => $business,
            'types' => BusinessType::cases(),
            'timezones' => timezone_identifiers_list(),
        ]);
    }

    public function update(UpdateBusinessRequest $request, CurrentBusiness $currentBusiness): RedirectResponse
    {
        $business = $this->current($currentBusiness);
        Gate::authorize('update', $business);

        /** @var User $user */
        $user = $request->user();

        /** @var array{name: string, type: string, timezone: string, brand_color?: string|null} $data */
        $data = $request->validated();
        $this->businesses->update($user, $business, $data);

        return redirect()->route('dashboard.business.edit')->with('status', 'Setările au fost salvate.');
    }

    public function switch(SwitchBusinessRequest $request): RedirectResponse
    {
        $request->session()->put(EnsureBusinessMember::SESSION_KEY, $request->integer('business_id'));

        return redirect()->route('dashboard');
    }

    private function current(CurrentBusiness $currentBusiness): Business
    {
        return $currentBusiness->get() ?? abort(403);
    }
}
