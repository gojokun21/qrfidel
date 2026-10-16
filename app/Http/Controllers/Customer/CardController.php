<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Cardurile clientului, de la toate afacerile (contul e global). Cardul cu QR vine în Sesiunea 7.
 */
class CardController extends Controller
{
    public function index(Request $request): View
    {
        /** @var Customer $customer */
        $customer = $request->user('customer');

        return view('customer.cards', [
            'customer' => $customer,
            'cards' => $customer->cards()->with('program.business')->latest('id')->get(),
        ]);
    }
}
