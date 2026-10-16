<?php

namespace App\Http\Controllers\Customer;

use App\Enums\ProgramStatus;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pagina publică a afacerii (SDD §5.1): ținta QR-ului de pe afiș, cu programele active și înscrierea.
 */
class BusinessPageController extends Controller
{
    public function __invoke(Request $request, Business $business): View
    {
        abort_unless($business->isActive(), 404);

        $programs = $business->programs()
            ->where('status', ProgramStatus::Active)
            ->oldest()
            ->get()
            ->each->setRelation('business', $business);

        /** @var Customer|null $customer */
        $customer = $request->user('customer');

        $cards = $customer?->cards()
            ->whereIn('program_id', $programs->modelKeys())
            ->get()
            ->keyBy('program_id');

        return view('customer.business', [
            'business' => $business,
            'programs' => $programs,
            'customer' => $customer,
            'cards' => $cards ?? collect(),
        ]);
    }
}
