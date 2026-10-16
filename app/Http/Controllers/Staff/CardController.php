<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\CardActionRequest;
use App\Http\Requests\Staff\StampCardRequest;
use App\Models\Card;
use App\Models\Program;
use App\Models\User;
use App\Services\Loyalty\ScanTicketService;
use App\Services\Loyalty\StaffCardActions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Ecranul cardului scanat și acțiunile angajatului (SDD §5.2). Fără tichet valid, ecranul e doar de citit.
 */
class CardController extends Controller
{
    public function __construct(private StaffCardActions $actions) {}

    public function show(Request $request, Card $card, ScanTicketService $tickets): View
    {
        Gate::authorize('view', $card);

        $card->load(['customer', 'program.business']);

        $ticket = $request->query('ticket');

        return view('staff.card', [
            'card' => $card,
            'program' => $card->program,
            // Cache-ul `rewards_available` nu scade la expirare: butonul se bazează pe recompensele reale.
            'rewards' => $card->usableRewardsCount(),
            'scan' => $tickets->check(is_string($ticket) ? $ticket : null, $card, $this->actor($request)),
            'transactions' => $card->transactions()->with(['staff', 'location'])->latest('id')->limit(5)->get(),
        ]);
    }

    public function stamp(StampCardRequest $request, Card $card): RedirectResponse|JsonResponse
    {
        Gate::authorize('stamp', $card);

        $result = $this->actions->stamp($card, $this->actor($request), $request->ticket(), $request->integer('quantity'), $request->idempotencyKey());

        return $this->done($request, $result->card, $result->summary());
    }

    public function redeem(CardActionRequest $request, Card $card): RedirectResponse|JsonResponse
    {
        Gate::authorize('redeem', $card);

        $result = $this->actions->redeem($card, $this->actor($request), $request->ticket(), $request->idempotencyKey());

        /** @var Program $program */
        $program = $card->program;

        return $this->done($request, $result->card, "Recompensă folosită: {$program->reward_title}.");
    }

    /**
     * @param  Card  $card  cardul blocat și actualizat de serviciu (fără încă un SELECT)
     */
    private function done(CardActionRequest $request, Card $card, string $status): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $status,
                'stamps' => $card->stamps_balance,
                'rewards' => $card->rewards_available,
            ]);
        }

        return redirect()
            ->route('staff.cards.show', ['card' => $card, 'ticket' => $request->ticket()])
            ->with('status', $status);
    }

    private function actor(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
