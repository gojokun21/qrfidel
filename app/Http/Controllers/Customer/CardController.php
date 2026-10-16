<?php

namespace App\Http\Controllers\Customer;

use App\Exceptions\InvalidQrToken;
use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\Customer;
use App\Services\Loyalty\QrTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Cardurile clientului, de la toate afacerile (contul e global), și QR-ul rotativ al fiecăruia (ADR 005, ADR 010).
 */
class CardController extends Controller
{
    public function index(Request $request): View
    {
        $customer = $this->customer($request);

        return view('customer.cards', [
            'customer' => $customer,
            'cards' => $customer->cards()->with('program.business')->latest('id')->get(),
        ]);
    }

    public function show(Request $request, Card $card, QrTokenService $qr): View
    {
        Gate::forUser($this->customer($request))->authorize('own', $card);

        $card->load('program.business');

        return view('customer.card', [
            'card' => $card,
            'program' => $card->program,
            'scannable' => $qr->isScannable($card),
        ]);
    }

    /**
     * Un QR nou la fiecare rotație (POST: revocă codul scurt anterior). Nu se păstrează în niciun cache.
     */
    public function qr(Request $request, Card $card, QrTokenService $qr): JsonResponse
    {
        try {
            // Proprietarul (CardPolicy::own, 404) și starea programului se verifică în serviciu.
            $token = $qr->issueForCustomer($card, $this->customer($request));
        } catch (InvalidQrToken $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json([
            'token' => $token->token,
            'shortCode' => $token->shortCode,
            'issuedAt' => $token->issuedAt->toIso8601String(),
            'expiresAt' => $token->expiresAt->toIso8601String(),
            'refreshSeconds' => (int) config('loyalty.qr.refresh_seconds'),
        ])->header('Cache-Control', 'no-store, private');
    }

    private function customer(Request $request): Customer
    {
        /** @var Customer */
        return $request->user('customer');
    }
}
