<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Layout-ul paginilor de autentificare, invitații și erori: un card centrat pe fundalul închis al brandului.
 */
class GuestLayout extends Component
{
    public function __construct(public string $title = '') {}

    public function render(): View
    {
        return view('layouts.guest');
    }
}
