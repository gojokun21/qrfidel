<?php

namespace App\Http\Controllers\Staff;

use App\Exceptions\StaffLocationMissing;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\ApproveStampRequestRequest;
use App\Models\Business;
use App\Models\Card;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Program;
use App\Models\StampRequest;
use App\Models\User;
use App\Services\Loyalty\StaffLocationService;
use App\Services\Loyalty\StampRequestService;
use App\Support\Tenancy\CurrentBusiness;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Panoul cu cererile de ștampilă de la tejghea (SDD §5.3, ADR 012): lista se reîmprospătează prin polling,
 * pe locația curentă a angajatului (aceeași alegere ca la scanare).
 */
class RequestController extends Controller
{
    public function __construct(
        private StaffLocationService $locations,
        private StampRequestService $requests,
    ) {}

    public function index(Request $request, CurrentBusiness $currentBusiness): View
    {
        $business = $this->current($currentBusiness);
        $user = $this->actor($request);
        $location = $this->location($request, $business);

        return view('staff.requests', [
            'locations' => $this->locations->selectable($user, $business),
            'location' => $location,
            'feed' => $location === null ? [] : $this->present($this->requests->openFor($location)),
            'pollSeconds' => (int) config('loyalty.requests.poll_seconds'),
        ]);
    }

    public function feed(Request $request, CurrentBusiness $currentBusiness): JsonResponse
    {
        $location = $this->location($request, $this->current($currentBusiness)) ?? throw new StaffLocationMissing;

        return response()
            ->json(['requests' => $this->present($this->requests->openFor($location))])
            ->header('Cache-Control', 'no-store, private');
    }

    public function approve(ApproveStampRequestRequest $request, StampRequest $stampRequest): RedirectResponse|JsonResponse
    {
        Gate::authorize('handle', $stampRequest);

        $result = $this->requests->approve($stampRequest, $this->actor($request), $request->integer('quantity'), $request->idempotencyKey());

        return $this->done($request, $result->summary());
    }

    public function reject(Request $request, StampRequest $stampRequest): RedirectResponse|JsonResponse
    {
        Gate::authorize('handle', $stampRequest);

        $this->requests->reject($stampRequest, $this->actor($request));

        return $this->done($request, 'Cerere respinsă.');
    }

    private function done(Request $request, string $message): RedirectResponse|JsonResponse
    {
        return $request->expectsJson()
            ? response()->json(['message' => $message])
            : redirect()->route('staff.requests')->with('status', $message);
    }

    /**
     * Ce vede angajatul despre o cerere: fără emailul întreg și fără public_id-ul cardului.
     *
     * @param  iterable<StampRequest>  $requests
     * @return list<array<string, mixed>>
     */
    private function present(iterable $requests): array
    {
        $rows = [];

        foreach ($requests as $stampRequest) {
            /** @var Card $card */
            $card = $stampRequest->card;
            /** @var Customer $customer */
            $customer = $card->customer;
            /** @var Program $program */
            $program = $card->program;

            $rows[] = [
                'id' => $stampRequest->id,
                'customer' => $customer->name ?: 'Client',
                'email' => $customer->maskedEmail(),
                'program' => $program->name,
                'stamps' => $card->stamps_balance,
                'required' => $program->stamps_required,
                'rewards' => $card->rewards_available,
                'maxStamps' => $program->quickStampCount(),
                'createdAt' => $stampRequest->created_at?->toIso8601String(),
                'expiresAt' => $stampRequest->expires_at->toIso8601String(),
                'approveUrl' => route('staff.requests.approve', $stampRequest),
                'rejectUrl' => route('staff.requests.reject', $stampRequest),
            ];
        }

        return $rows;
    }

    private function location(Request $request, Business $business): ?Location
    {
        return $this->locations->current($this->actor($request), $business, $request->session()->get(ScanController::LOCATION_SESSION_KEY));
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
