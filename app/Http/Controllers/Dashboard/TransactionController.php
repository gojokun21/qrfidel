<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\VoidTransactionRequest;
use App\Models\Business;
use App\Models\Card;
use App\Models\CardTransaction;
use App\Models\Membership;
use App\Models\User;
use App\Services\Loyalty\TransactionLog;
use App\Services\Loyalty\VoidService;
use App\Support\Tenancy\CurrentBusiness;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Pagina „Tranzacții” (manager+, ADR 013): ultimele mișcări ale afacerii și anularea lor (SDD §5.6).
 * Formularul de anulare se folosește și pe ecranul cardului scanat.
 */
class TransactionController extends Controller
{
    public function __construct(private TransactionLog $log) {}

    public function index(Request $request, CurrentBusiness $currentBusiness): View
    {
        $business = $currentBusiness->get() ?? abort(403);
        $membership = $this->membership($request, $business);

        $locationId = $request->integer('location') ?: null;
        $type = TransactionType::tryFrom($request->string('type')->toString());

        // Restricția pe locația managerului o aplică TransactionLog; aici doar ascundem selectorul.
        $transactions = $this->log->paginate($membership, $locationId, $type);
        $restricted = $this->log->ownLocation($membership) !== null;

        return view('dashboard.transactions.index', [
            'business' => $business,
            'transactions' => $transactions,
            'voidable' => $this->log->voidableIds($membership, $business, $transactions->items()),
            'locations' => $restricted ? collect() : $business->locations()->orderBy('name')->get(),
            'types' => TransactionType::cases(),
            'filters' => ['location' => $locationId, 'type' => $type],
        ]);
    }

    public function void(VoidTransactionRequest $request, CardTransaction $transaction, VoidService $voids): RedirectResponse
    {
        /** @var Card $card */
        $card = $transaction->card;
        Gate::authorize('void', $card);

        /** @var User $actor */
        $actor = $request->user();
        $voids->void($transaction, $actor, $request->idempotencyKey(), $request->reason());

        return back()->with('status', 'Tranzacția a fost anulată.');
    }

    private function membership(Request $request, Business $business): Membership
    {
        /** @var User $user */
        $user = $request->user();

        return $user->membershipIn($business) ?? abort(403);
    }
}
