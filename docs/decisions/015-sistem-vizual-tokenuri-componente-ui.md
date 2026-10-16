# 015 – Tokenuri semantice și componente `x-ui` pentru tot frontend-ul

**Data:** 2026-10-16 · **Sesiune:** 11b (în afara listei din SDD §11.4, la cererea utilizatorului) · Extinde [ADR 007](007-sistem-vizual-sidebar-landing.md)

## Context
ADR 007 a introdus paleta (espresso / gold / cream) și câteva clase `@apply` (`.btn-*`, `.link`). Paginile foloseau însă direct nuanțele paletei și ale Tailwind (`espresso-500`, `red-600`, `emerald-50`…), iar containerul paginii, antetul, tile-urile KPI, badge-urile, pastilele de filtru și mesajele de stare erau copiate în fiecare view. O schimbare de culoare sau de densitate cerea editarea a zeci de fișiere. Paginarea era cea implicită Laravel (gri/indigo, în engleză), iar paginile nu aveau `h1`.

## Decizii
1. **Tailwind rămâne 3.4** (fără upgrade la v4). Tokenurile stau în `tailwind.config.js`: paleta brandului + un strat **semantic** (`canvas`, `surface`, `ink`, `line`, `primary`, `inverse`, `success`, `warning`, `danger`, `focus`), raze (`control`, `card`, `panel`), umbre (`card`, `overlay`), lățimi (`content`, `form`, `narrow`). Paginile aplicației folosesc doar tokenurile semantice.
2. **Componente Blade anonime pe foldere**: `ui/` (generale), `layout/`, `loyalty/` (domeniu), `brand/`. Variantele sunt mapări PHP cu clase complete. Clasele `.btn-*` și `.link` din `app.css` au fost scoase, la fel componentele Breeze (`x-primary-button`, `x-text-input` etc.).
3. **`x-app-layout`** primește `title`, `eyebrow`, `description`, `back`, `width` și sloturile `actions` / `meta`. Afișează `h1`, `<title>`, containerul și mesajele flash, deci paginile nu le mai repetă. Layout-ul client și cel guest primesc și ele `title`.
4. **Formulare accesibile**: `x-ui.field` + `x-ui.input/select/textarea` leagă controlul de indiciu și de eroare (`aria-describedby`, `aria-invalid`), prin `@aware` și helperul `App\View\FormControl`. Erorile au iconiță, nu doar culoare.
5. **Dialoguri și drawer**: `x-ui.modal` are `role="dialog"`, `aria-modal`, titlu obligatoriu, focus în dialog, Tab blocat în dialog și revenire pe declanșator. Drawer-ul din sidebar mută focusul în meniu și face conținutul `inert` cât e deschis. Am adăugat linkul „Sari la conținut”.
6. **Paginare proprie** în `resources/views/vendor/pagination/tailwind.blade.php`, în română.
7. **Landing-ul** își păstrează direcția artistică (paleta brută, gradiente); doar butoanele lui folosesc `x-ui.button`.
8. Confirmările distructive rămân `confirm()` nativ (accesibil, fără dependențe).

## Consecințe
- O schimbare de culoare, rază sau densitate se face în `tailwind.config.js` sau într-o singură componentă. Ghidul e în `docs/ui.md`.
- Textele Breeze (auth, profil) sunt în continuare în engleză (`__()` cu locale `en`); traducerea e separată de această refactorizare.
- `@tailwindcss/vite` (v4) e încă în `package.json`, dar nu e folosit; buildul merge prin PostCSS cu Tailwind 3.
