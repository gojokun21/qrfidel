<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\MagicLinkRequest;
use App\Models\Business;
use App\Models\Location;
use App\Services\Loyalty\MagicLinkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Conectarea clientului cu magic link (SDD §5.1, ADR 009). Rutele cu link sunt protejate de `signed`.
 */
class MagicLinkController extends Controller
{
    public function __construct(private readonly MagicLinkService $magicLinks) {}

    public function create(): View
    {
        return view('customer.login');
    }

    public function store(MagicLinkRequest $request): RedirectResponse
    {
        $this->magicLinks->send($request->email(), (string) $request->ip(), $request->business(), $request->counterLocation());

        return back()->with('magic_link_sent', $request->email());
    }

    /**
     * GET pe link nu loghează: scannerele de linkuri din clienții de email îl deschid înaintea omului.
     */
    public function show(Request $request): View
    {
        return view('customer.magic-confirm', [
            'email' => self::signedParam($request, 'email'),
            'action' => $request->fullUrl(),
        ]);
    }

    public function consume(Request $request): RedirectResponse
    {
        // Doar din query string: `signed` a verificat URL-ul, iar câmpurile din corpul POST-ului le-ar suprascrie.
        $customer = $this->magicLinks->consume(
            self::signedParam($request, 'email'),
            self::signedParam($request, 'nonce'),
        );

        Auth::guard('customer')->login($customer, remember: true);
        $request->session()->regenerate();

        // Doar spre tejgheaua sau pagina unei afaceri (din linkul semnat), niciodată spre un URL arbitrar.
        $counter = Location::findByCounterToken(self::signedParam($request, 'l'));
        if ($counter !== null) {
            return redirect()->route('customer.counter', $counter->counter_token);
        }

        $business = Business::findActiveBySlug(self::signedParam($request, 'b'));

        return $business !== null
            ? redirect()->route('customer.business', $business)
            : redirect()->route('customer.cards');
    }

    private static function signedParam(Request $request, string $key): string
    {
        $value = $request->query($key);

        return is_string($value) ? $value : '';
    }

    /**
     * Fără invalidate(): sesiunea poate ține și un angajat logat pe guard-ul `web` în același browser.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('customer')->logout();

        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()->route('customer.login');
    }
}
