# 007 – Sistem vizual, sidebar în dashboard și landing page

**Data:** 2026-10-16 · **Sesiune:** 04b (în afara listei din SDD §11.4, la cererea utilizatorului)

## Context
Dashboard-ul folosea navbar-ul Breeze (gri/indigo, sus), iar `/` era încă welcome-ul Laravel. Utilizatorul a cerut un sidebar în stânga și un landing în stilul kof.ru (header închis translucid, accent auriu, titlu script, bandă marquee, carduri cu colțuri mari, footer negru).

## Decizii
1. **Tokenuri în `tailwind.config.js`**: `espresso` (50–950, fundaluri închise și text), `gold` (accent, `gold-500` = `#CC9933`), `cream` (suprafețe calde). Paginile nu mai folosesc `gray-*` / `indigo-*`.
2. **Fonturi** din Bunny Fonts: Onest (UI) și Kaushan Script doar pe landing (`font-script`). Yellowtail a fost respins: nu are `ș`/`ț` cu virgulă dedesubt.
3. **Clase comune** în `resources/css/app.css`: `.btn-gold`, `.btn-dark`, `.btn-outline`, `.btn-danger`, `.link`, `.grain`. Componentele Blade (`x-primary-button` etc.) le folosesc, deci un buton se schimbă într-un singur loc. Animațiile se opresc pentru `prefers-reduced-motion`.
4. **Sidebar** (`layouts/sidebar.blade.php`): fix pe desktop (`w-72`), drawer pe mobil. Datele vin din `App\View\Composers\SidebarComposer`, nu din `@php` în Blade. Rolul se citește din pivotul afacerilor deja încărcate, deci linkurile nu mai fac câte un `roleIn()` fiecare. Nu afișăm linkuri pentru module care nu există încă (Programe, Rapoarte); se adaugă în S05/S11.
5. **Afacerea pe pagini fără middleware `business`** (profil, afacere nouă): sidebar-ul o alege cu `App\Support\Tenancy\BusinessResolver`, aceeași regulă ca în `EnsureBusinessMember` (logica a fost mutată acolo, nu duplicată). Rezultatul e folosit doar pentru afișare: nu se scrie în sesiune și nu se setează contextul de tenancy. O afacere suspendată nu primește linkuri.
6. **Landing** (`landing.blade.php`, ruta `home`, layout `x-marketing-layout`) fără imagini externe: hero cu gradient, grain SVG și un mockup animat de card (Alpine). O fotografie se poate adăuga mai târziu fără schimbări de structură.
7. `x-card` primește prop-ul `flush` (fără padding). Înainte, `class="p-0"` se combina cu `p-6`, iar `p-6` câștiga.

## Consecințe
- Paginile noi folosesc tokenurile și componentele de mai sus.
- Paginile de auth (Breeze) au doar culorile noi; textele lor sunt încă în engleză.
