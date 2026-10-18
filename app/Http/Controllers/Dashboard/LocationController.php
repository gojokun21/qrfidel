<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\LocationRequest;
use App\Models\Business;
use App\Models\Location;
use App\Models\User;
use App\Services\Loyalty\IdempotentCreation;
use App\Services\Loyalty\LocationService;
use App\Support\Tenancy\CurrentBusiness;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class LocationController extends Controller
{
    public function __construct(private LocationService $locations, private IdempotentCreation $idempotent) {}

    public function index(CurrentBusiness $currentBusiness): View
    {
        $business = $this->current($currentBusiness);
        Gate::authorize('viewAny', [Location::class, $business]);

        return view('dashboard.locations.index', [
            'business' => $business,
            'locations' => $business->locations()->orderBy('name')->get(),
        ]);
    }

    public function create(CurrentBusiness $currentBusiness): View
    {
        Gate::authorize('create', [Location::class, $this->current($currentBusiness)]);

        return view('dashboard.locations.create', ['location' => new Location]);
    }

    public function store(LocationRequest $request, CurrentBusiness $currentBusiness): RedirectResponse
    {
        $business = $this->current($currentBusiness);
        Gate::authorize('create', [Location::class, $business]);

        /** @var array{name: string, address?: string|null} $data */
        $data = $request->validated();
        $actor = $this->actor($request);
        $this->idempotent->once($actor, 'location', $request->string('idempotency_key')->value(),
            fn () => $this->locations->create($actor, $business, $data));

        return redirect()->route('dashboard.locations.index')->with('status', 'Locația a fost adăugată.');
    }

    public function edit(Location $location): View
    {
        Gate::authorize('update', $location);

        return view('dashboard.locations.edit', ['location' => $location]);
    }

    public function update(LocationRequest $request, Location $location): RedirectResponse
    {
        Gate::authorize('update', $location);

        /** @var array{name: string, address?: string|null} $data */
        $data = $request->validated();
        $this->locations->update($this->actor($request), $location, $data);

        return redirect()->route('dashboard.locations.index')->with('status', 'Locația a fost salvată.');
    }

    public function destroy(Request $request, Location $location): RedirectResponse
    {
        Gate::authorize('delete', $location);

        $this->locations->delete($this->actor($request), $location);

        return redirect()->route('dashboard.locations.index')->with('status', 'Locația a fost ștearsă.');
    }

    public function regenerateCounterToken(Request $request, Location $location): RedirectResponse
    {
        Gate::authorize('update', $location);

        $this->locations->regenerateCounterToken($this->actor($request), $location);

        return redirect()->route('dashboard.locations.index')->with('status', 'Codul de tejghea a fost regenerat. Tipărește din nou QR-ul locației.');
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
