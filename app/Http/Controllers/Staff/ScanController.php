<?php

namespace App\Http\Controllers\Staff;

use App\Exceptions\StaffLocationMissing;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\ChooseLocationRequest;
use App\Http\Requests\Staff\ResolveScanRequest;
use App\Models\Business;
use App\Models\User;
use App\Services\Loyalty\QrTokenService;
use App\Services\Loyalty\ScanTicketService;
use App\Services\Loyalty\StaffLocationService;
use App\Support\Tenancy\CurrentBusiness;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Scanarea la casă (SDD §5.2, ADR 011): camera sau codul scurt → resolve → ecranul cardului, cu tichet de scanare.
 */
class ScanController extends Controller
{
    /** Locația aleasă de angajatul fără locație fixă. */
    public const LOCATION_SESSION_KEY = 'staff_location_id';

    public function __construct(private StaffLocationService $locations) {}

    public function show(Request $request, CurrentBusiness $currentBusiness): View
    {
        $business = $this->current($currentBusiness);
        $user = $this->actor($request);

        return view('staff.scan', [
            'locations' => $this->locations->selectable($user, $business),
            'location' => $this->locations->current($user, $business, $request->session()->get(self::LOCATION_SESSION_KEY)),
        ]);
    }

    public function location(ChooseLocationRequest $request): RedirectResponse
    {
        $request->session()->put(self::LOCATION_SESSION_KEY, $request->integer('location_id'));

        // Alegerea se face și din panoul de cereri: angajatul rămâne pe pagina de unde a ales.
        return redirect()->back(fallback: route('staff.scan'));
    }

    public function resolve(ResolveScanRequest $request, CurrentBusiness $currentBusiness, QrTokenService $qr, ScanTicketService $tickets): RedirectResponse|JsonResponse
    {
        $user = $this->actor($request);
        $location = $this->locations->current($user, $this->current($currentBusiness), $request->session()->get(self::LOCATION_SESSION_KEY))
            ?? throw new StaffLocationMissing;

        $card = $qr->resolve($request->string('code')->toString(), $user, $location);
        $url = route('staff.cards.show', ['card' => $card, 'ticket' => $tickets->issue($card, $user, $location)]);

        return $request->expectsJson() ? response()->json(['redirect' => $url]) : redirect()->to($url);
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
