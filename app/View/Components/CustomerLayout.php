<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Layout-ul clientului (pagina afacerii, login, cardurile): mobile-first, baza viitorului PWA (Sesiunea 7).
 */
class CustomerLayout extends Component
{
    public function __construct(public string $title = '') {}

    public function render(): View
    {
        return view('layouts.customer');
    }
}
