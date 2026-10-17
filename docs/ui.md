# Interfața: sistem vizual și componente

Ghid scurt pentru cine modifică frontend-ul. Decizia de design e în [ADR 015](decisions/015-sistem-vizual-tokenuri-componente-ui.md); paleta în [ADR 017](decisions/017-brand-loyup.md).

Stack: Blade + Alpine.js + **Tailwind 3.4** (PostCSS, `@tailwindcss/forms`). Nu există alt framework de UI.

## 1. Unde sunt tokenurile

Totul stă în **`tailwind.config.js`**:

| Ce | Unde | Exemple de clase |
|---|---|---|
| Paleta brandului (jade, navy, steel – ADR 017) | constantele `jade`, `navy`, `steel` | doar în tokenuri și pe landing |
| Culori semantice | obiectul `semantic` | vezi tabelul de mai jos |
| Raze | `borderRadius` | `rounded-control` (butoane, câmpuri), `rounded-card`, `rounded-panel` |
| Umbre | `boxShadow` | `shadow-card`, `shadow-overlay` (dialoguri, meniuri) |
| Lățimi de conținut | `maxWidth` | `max-w-content` (pagini), `max-w-form`, `max-w-narrow` |
| Tipografie | `fontFamily`, `letterSpacing` | `font-sans` (Onest), `tracking-eyebrow` |
| Fundalul închis cu lumină aurie | `backgroundImage` | `bg-glow` |

Culorile semantice (rolul, nu nuanța):

| Rol | Token | Folosire |
|---|---|---|
| fundal | `canvas` | `bg-canvas` – fundalul paginii |
| suprafață | `surface`, `surface-muted`, `surface-strong` | carduri, câmpuri; pastile și zone secundare |
| text | `ink`, `ink-secondary`, `ink-muted` | titluri/text principal; paragrafe; meta și indicii |
| bordură | `line`, `line-strong` | separatoare și carduri; câmpuri de formular |
| principal | `primary` (+ `hover`, `active`, `fg`, `soft`, `line`, `ink`) | acțiunea principală (verde), linkuri (`text-primary-ink`) |
| accent | `accent` (+ `hover`, `fg`, `soft`, `line`, `ink`) | recompense, ștampile, evidențieri pe fundal închis (verdele din logo) |
| zone închise | `inverse` (+ `deep`, `raised`, `line`, `fg`, `muted`) | sidebar, header client, cardul de fidelitate, butonul `neutral` |
| stări | `success`, `warning`, `danger` (+ `soft`, `line`, `ink`) | mesaje, badge-uri, acțiuni distructive |
| focus | `focus` | `focus-visible:ring-focus` |

### Cum schimb global…
- **culorile**: modifici valorile din `semantic` (sau din paletă). Paginile nu conțin hex-uri, deci nu se atinge nimic altceva. Rulează `npm run build`.
- **tipografia**: `fontFamily.sans` + linkul de fonturi din `resources/views/layouts/partials/head.blade.php`. Dimensiunile titlurilor sunt în layout (`h1` din `layouts/app.blade.php`) și în `x-ui.card` (titlul cardului).
- **spațierea și densitatea**: paddingul cardurilor e în `x-ui.card` / `x-ui.stat`, cel al controalelor în `x-ui.button` (`$sizes`) și `App\View\FormControl`, distanța dintre blocurile paginii (`space-y-6`) și marginile laterale în `layouts/app.blade.php`.
- **razele / umbrele**: `borderRadius` / `boxShadow` din config.

## 2. Componente

```
resources/views/components/
  ui/        componente generale, fără logică de business
  layout/    bucăți de layout: flash, sidebar-link
  loyalty/   componente de domeniu: card de fidelitate, status program / afacere, cerere în așteptare
  brand/     logo, wordmark
  landing/   ilustrațiile landing-ului (`seal`: ștampila din mockup-ul cardului)
resources/views/layouts/    app (staff/owner/admin), customer (PWA), guest (auth), marketing (landing)
```

Componentele `ui` (prefix `x-ui.`):

| Componentă | API |
|---|---|
| `button` | `variant`: primary · neutral · secondary · ghost · danger · danger-ghost · inverse · brand (gradient verde) · light (alb pe fundal pastel); `size`: sm · md · lg · xl; `href` (→ `<a>`); `icon`; `pill` (colțuri complet rotunjite). Implicit `type="submit"`. `brand`, `light` și `pill` sunt pentru paginile publice (ADR 018). |
| `link` | link de text; `variant`: primary · muted |
| `field` | `label`, `for` (id-ul controlului), `error` (cheia erorii, implicit `for`), `hint`, `optional`, `error-bag` |
| `input`, `select`, `textarea` | atributele HTML obișnuite; `:invalid` opțional. În `x-ui.field` (cu `id` = `for`) primesc singure `aria-invalid` și `aria-describedby`; în afara unui câmp nu sunt marcate niciodată invalide. |
| `checkbox` | `label`, `description` |
| `error` | `messages`, `id` – folosit de `field`, sau singur pentru câmpuri fără `field` |
| `card` | `flush` (fără padding, pentru liste `divide-y divide-line`), `title`, `description`, slotul `actions` |
| `stat` | `label`, `value`, `icon`, `hint`; slotul = link |
| `badge` | `tone`: neutral · muted · primary · accent · success · warning · danger · inverse |
| `alert` | `tone`: info · success · warning · danger; `title`. Are iconiță și `role` (nu doar culoare). |
| `empty-state` | `icon`, `title`; slotul = explicația; slotul `actions` |
| `filter-link` | `href`, `active` – pastile de filtru în `<nav aria-label="…">` |
| `table` (+ `table.th`, `table.td`) | slotul `head`; `numeric` pe celule (aliniere dreapta, cifre tabulare). Pe mobil se derulează în card. |
| `avatar` | `name`, `size` (sm · md · lg), `color` (hex, cu contrast automat) |
| `modal` | `name`, `title`, `show`, `maxWidth`. Deschidere: `$dispatch('open-modal', 'nume')`. Focus în dialog, Tab blocat în dialog, Escape, revenire pe declanșator. |
| `icon` | `name` (vezi `@case`-urile din fișier) |

Paginarea (`$paginator->links()`) folosește `resources/views/vendor/pagination/tailwind.blade.php`.

### Cum extind o componentă
1. Adaugi varianta în maparea din componentă (de ex. `$variants` din `button.blade.php`) cu **clase complete**. Nu construi nume de clase din bucăți (`'bg-'.$tone`), fiindcă Tailwind nu le găsește la build.
2. Dacă varianta are nevoie de o culoare nouă, adaugi întâi tokenul în `semantic`.
3. Documentezi varianta în comentariul din capul componentei și în tabelul de mai sus.

Creezi o componentă nouă doar pentru un element care se repetă sau are comportament propriu. Un container folosit o singură dată rămâne în pagină.

## 3. O pagină nouă

```blade
<x-app-layout title="Titlul paginii" description="O propoziție despre pagină." width="wide">
    <x-slot:actions>
        <x-ui.button :href="route('...')" icon="plus">Acțiunea principală</x-ui.button>
    </x-slot:actions>

    <x-ui.card flush title="Listă">
        @if ($items->isEmpty())
            <x-ui.empty-state icon="list" title="Nimic încă." />
        @else
            <ul class="divide-y divide-line">…</ul>
        @endif
    </x-ui.card>

    {{ $items->links() }}
</x-app-layout>
```

- `x-app-layout` pune titlul (`h1` și `<title>`), linkul de întoarcere (`back`, `back-label`), `eyebrow`, `description`, sloturile `actions` / `meta`, containerul (`width`: `wide` · `form` · `narrow`) și **mesajele flash**. Nu pune `x-layout.flash` în paginile acestui layout.
- Clientul (PWA): `x-customer-layout :title="…"`. Aici fiecare pagină își pune `x-layout.flash` unde are sens.
- Auth / invitații / erori: `x-guest-layout title="…"`; `heading` / `subheading` pun titlul vizibil în card, pentru paginile care nu au un `h1` propriu (login, register).
- O singură acțiune `primary` pe ecran; restul `secondary` / `ghost`.

## 4. Excepții CSS rămase (și de ce)

| Unde | Ce | De ce |
|---|---|---|
| `resources/css/app.css` | `[x-cloak] { display: none !important }` | tiparul documentat de Alpine; trebuie să bată orice `display` |
| `resources/css/app.css` | `prefers-reduced-motion` cu `!important` | oprește animațiile decorative indiferent de clasele `animate-*` |
| `loyalty/card`, `customer/card` | `[&>svg]:h-full [&>svg]:w-full` | SVG-ul vine din `x-html` (iconițe, QR), deci nu i se pot pune clase |
| `loyalty/card`, `ui/avatar`, wizard | `style="background-color: …"` | culoarea vine din datele afacerii/programului, nu din tokenuri |
| `programs/index`, wizard | `md:grid-cols-[16rem_minmax(0,1fr)]`, `lg:grid-cols-[minmax(0,1fr)_22rem]` | grile cu o coloană fixă, folosite o dată |
| `landing.blade.php`, `layouts/marketing.blade.php` | paleta brută, valori arbitrare (gradiente, mărimi de font), culori hex în SVG-urile decorative | direcție artistică proprie a landing-ului (ADR 018); nu face parte din interfața aplicației |
| paginile publice (landing, guest, customer) | `bg-hero`, `bg-dots`, `shadow-soft`, `shadow-float` | tokenurile stilului public (fundal pastel, carduri care plutesc); aplicația internă nu le folosește |

## 5. Verificare

`npm run build` după orice schimbare de clase sau de tokenuri. `tests/Feature/Ui/UiComponentsTest.php` acoperă legătura câmp ↔ eroare, mesajele flash și paginarea; `SidebarTest` acoperă navigarea.
