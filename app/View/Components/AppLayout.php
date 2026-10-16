<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Layout-ul aplicației (staff, owner, admin): sidebar, antetul paginii și containerul conținutului.
 *
 * - `title`: titlul paginii (h1 și <title>); `eyebrow` / `description`: textul de deasupra / de sub titlu;
 * - `back` + `backLabel`: linkul de întoarcere de deasupra titlului;
 * - `width`: lățimea conținutului – `wide` (liste, rapoarte), `form` (formulare), `narrow` (ecranele de lucru ale angajatului).
 *
 * Sloturi: `actions` (butoanele din dreapta titlului), `meta` (badge-uri lângă titlu).
 */
class AppLayout extends Component
{
    public const WIDTHS = [
        'wide' => 'max-w-content',
        'form' => 'max-w-form',
        'narrow' => 'max-w-narrow',
    ];

    public function __construct(
        public string $title = '',
        public ?string $eyebrow = null,
        public ?string $description = null,
        public ?string $back = null,
        public ?string $backLabel = null,
        public string $width = 'wide',
    ) {}

    public function widthClass(): string
    {
        return self::WIDTHS[$this->width] ?? self::WIDTHS['wide'];
    }

    public function render(): View
    {
        return view('layouts.app');
    }
}
