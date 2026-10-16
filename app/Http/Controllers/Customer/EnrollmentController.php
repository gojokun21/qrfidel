<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Program;
use App\Services\Loyalty\EnrollmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EnrollmentController extends Controller
{
    /**
     * Programul vine prin binding scoped pe afacere: un program al altei afaceri dă 404.
     */
    public function __invoke(Request $request, Business $business, Program $program, EnrollmentService $enrollments): RedirectResponse
    {
        abort_unless($business->isActive(), 404);

        /** @var Customer $customer */
        $customer = $request->user('customer');

        $result = $enrollments->enroll($customer, $program);

        // Direct pe card: progresul 0/N, QR-ul și „Adaugă pe ecranul principal” (SDD §5.1).
        return redirect()->route('customer.cards.show', $result->card)->with('status', $result->created
            ? "Te-ai înscris în „{$program->name}”."
            : "Ești deja înscris în „{$program->name}”.");
    }
}
