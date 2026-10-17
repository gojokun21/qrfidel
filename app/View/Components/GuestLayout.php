<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Layout-ul paginilor de autentificare, invitații și erori: un card alb centrat pe fundalul pastel al brandului.
 * `heading` / `subheading` = titlul vizibil (h1) din card, pentru paginile care nu-și pun singure unul.
 */
class GuestLayout extends Component
{
    public function __construct(
        public string $title = '',
        public ?string $heading = null,
        public ?string $subheading = null,
    ) {}

    public function render(): View
    {
        return view('layouts.guest');
    }
}
