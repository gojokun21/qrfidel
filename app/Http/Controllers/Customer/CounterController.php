<?php

namespace App\Http\Controllers\Customer;

use App\Enums\ProgramStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Program;
use App\Services\Loyalty\StampRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * QR-ul de pe tejghea (SDD §5.3, ADR 012): `/l/{counter_token}` → „Cere ștampilă” → cererea pending, pe care
 * angajatul o aprobă din panou. Tokenul identifică locația; după regenerare, QR-ul vechi dă 404.
 */
class CounterController extends Controller
{
    public function __construct(private readonly StampRequestService $requests) {}

    public function show(Request $request, string $token): View
    {
        $location = Location::findByCounterToken($token) ?? abort(404);
        $business = $location->business;

        $programs = $business->programs()
            ->where('status', ProgramStatus::Active)
            ->oldest()
            ->get()
            ->each->setRelation('business', $business);

        $customer = $this->customer($request);

        $cards = $customer?->cards()
            ->whereIn('program_id', $programs->modelKeys())
            ->get()
            ->keyBy('program_id') ?? collect();

        return view('customer.counter', [
            'business' => $business,
            'location' => $location,
            'programs' => $programs,
            'cards' => $cards,
            'requests' => $this->requests->latestForCards($cards->pluck('id')->values()->all()),
        ]);
    }

    public function store(Request $request, string $token, Program $program): RedirectResponse
    {
        $location = Location::findByCounterToken($token) ?? abort(404);

        /** @var Customer $customer */
        $customer = $this->customer($request);

        $stampRequest = $this->requests->request($customer, $location, $program);

        return redirect()->route('customer.cards.show', $stampRequest->card()->withoutGlobalScopes()->firstOrFail());
    }

    private function customer(Request $request): ?Customer
    {
        /** @var Customer|null */
        return $request->user('customer');
    }
}
