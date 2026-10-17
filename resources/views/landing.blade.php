@php
    // Circuitul din hero (viewBox 1000 × 380): liniile pleacă din cipul central (452–548 × 142–238) spre cipuri cu iconițe
    // sau spre puncte simple. Partea dreaptă e oglindirea celei stângi; doar iconițele diferă.
    $circuit = [
        ['d' => 'M452 160 H380 C350 160 350 70 320 70 H140', 'end' => [140, 70], 'icons' => ['coffee', 'utensils']],
        ['d' => 'M452 178 H330 C300 178 300 128 270 128 H240', 'end' => [240, 128], 'icons' => null],
        ['d' => 'M452 196 H90', 'end' => [90, 196], 'icons' => ['qr', 'gift']],
        ['d' => 'M452 214 H390 C360 214 360 318 330 318 H170', 'end' => [170, 318], 'icons' => ['scissors', 'car']],
        ['d' => 'M452 226 H350 C325 226 325 262 300 262 H272', 'end' => [272, 262], 'icons' => null],
    ];
    $vertical = [['d' => 'M490 142 V36', 'end' => [490, 36]], ['d' => 'M510 142 V84', 'end' => [510, 84]], ['d' => 'M490 238 V336', 'end' => [490, 336]], ['d' => 'M510 238 V296', 'end' => [510, 296]]];

    // Cifrele sunt fapte despre produs (SDD), nu statistici inventate. `p` = cât din arc e plin.
    $results = [
        ['label' => 'Configurarea unui program', 'icon' => 'sliders', 'value' => '2 min', 'p' => 0.35, 'note' => 'template'],
        ['label' => 'O ștampilă la tejghea', 'icon' => 'bolt', 'value' => '~2 s', 'p' => 0.55, 'note' => 'scanare'],
        ['label' => 'Valabilitatea codului QR', 'icon' => 'clock', 'value' => '90 s', 'p' => 0.8, 'note' => 'rotativ'],
        ['label' => 'Tranzacții în jurnal', 'icon' => 'list', 'value' => '100%', 'p' => 1.0, 'note' => 'audit'],
    ];
    // Capătul arcului: cercul are centrul (130, 150) și raza 100, deci arcul plin merge de la (30, 150) la (130, 50)
    // și punctul de la 100% rămâne în interiorul SVG-ului (150 × 150).
    $arcEnd = fn (float $p): array => [
        round(130 + 100 * cos(deg2rad(180 + 90 * $p)), 1),
        round(150 + 100 * sin(deg2rad(180 + 90 * $p)), 1),
    ];

    // Exemplele vin din template-urile programelor (SDD §7); frizeria e un program personalizat.
    $audiences = [
        ['title' => 'Cafenele', 'icon' => 'coffee', 'stamps' => 5, 'reward' => '1 cafea gratis', 'rule' => 'Cooldown 30 min', 'text' => 'Clientul revine pentru a cincea cafea, nu pentru cafeneaua de vizavi.'],
        ['title' => 'Restaurante', 'icon' => 'utensils', 'stamps' => 8, 'reward' => 'Desert din partea casei', 'rule' => 'O ștampilă pe masă', 'text' => 'Fiecare masă contează, iar desertul gratuit aduce înapoi toată familia.'],
        ['title' => 'Spălătorii auto', 'icon' => 'car', 'stamps' => 10, 'reward' => 'O spălare gratis', 'rule' => 'Maximum 1 pe zi', 'text' => 'Șoferii fideli sunt răsplătiți, fără carduri de carton uitate în torpedou.'],
        ['title' => 'Frizerii', 'icon' => 'scissors', 'stamps' => 6, 'reward' => 'O tunsoare gratis', 'rule' => 'Program personalizat', 'text' => 'Reguli pe care le alegi tu: câte ștampile, ce recompensă, cât e valabilă.'],
    ];

    $safeguards = [
        ['icon' => 'qr', 'title' => 'QR semnat, valabil 90 de secunde', 'text' => 'Codul de pe card se schimbă singur și poate fi folosit o singură dată. Un screenshot vechi nu mai funcționează.'],
        ['icon' => 'clock', 'title' => 'Cooldown și limită zilnică', 'text' => 'Tu decizi câte ștampile pe vizită și cât timp trebuie să treacă între ele. Regulile se aplică automat.'],
        ['icon' => 'shield', 'title' => 'Un click, o singură ștampilă', 'text' => 'Dublu-click, internet care cade, butonul apăsat de două ori? Ștampila se adaugă o singură dată.'],
        ['icon' => 'list', 'title' => 'Jurnal complet', 'text' => 'Vezi cine a dat fiecare ștampilă, unde și când. Greșelile se anulează transparent, nimic nu se șterge.'],
    ];

    $roles = [
        ['role' => 'Pentru patron', 'icon' => 'chart', 'title' => 'Vezi cine revine și ce aduce programul.', 'points' => ['Programe din template sau personalizate', 'Rapoarte pe locații și angajați', 'Echipă cu roluri: owner, manager, staff']],
        ['role' => 'Pentru angajat', 'icon' => 'scan', 'title' => 'O ștampilă în două secunde, fără greșeli.', 'points' => ['Scanare cu camera telefonului', 'Confirmare dintr-un click pentru cererile clienților', 'Anulare transparentă, cu motiv']],
        ['role' => 'Pentru client', 'icon' => 'phone', 'title' => 'Cardul e mereu în telefon.', 'points' => ['Intră cu emailul, fără parolă', 'Progresul și recompensele la vedere', 'Se instalează ca aplicație, dacă vrea']],
    ];
@endphp

<x-marketing-layout title="Carduri de fidelitate digitale" description="LoyUp: carduri de fidelitate digitale cu ștampile și QR pentru cafenele, restaurante, spălătorii și frizerii.">
    {{-- 1. Hero: container pastel rotunjit, titlu centrat și circuitul cu simbolul LoyUp în centru. --}}
    <div class="px-3 pt-3 sm:px-5 sm:pt-5">
        <section class="relative isolate overflow-hidden rounded-[2rem] bg-hero px-5 pb-6 pt-32 sm:rounded-[2.5rem] sm:pt-40">
            {{-- Liniile subțiri din colțuri, ca o continuare a circuitului. --}}
            <svg class="pointer-events-none absolute inset-x-0 top-0 -z-10 hidden h-72 w-full text-navy-200 lg:block" viewBox="0 0 1440 288" preserveAspectRatio="none" fill="none" aria-hidden="true">
                <path d="M0 132 H210 C240 132 250 148 270 172 L330 244 C346 262 360 270 390 270 H470" stroke="currentColor" vector-effect="non-scaling-stroke"/>
                <path d="M1440 132 H1230 C1200 132 1190 148 1170 172 L1110 244 C1094 262 1080 270 1050 270 H970" stroke="currentColor" vector-effect="non-scaling-stroke"/>
            </svg>
            <div class="absolute inset-0 -z-10 bg-dots bg-[length:22px_22px] [mask-image:radial-gradient(ellipse_at_center,black_20%,transparent_70%)]" aria-hidden="true"></div>

            <div class="mx-auto max-w-3xl text-center">
                <p class="inline-flex items-center gap-2 rounded-full border border-white bg-white/70 py-1.5 pl-2 pr-4 text-sm font-medium text-ink-secondary shadow-soft backdrop-blur">
                    <span class="rounded-full bg-jade-50 px-2 py-0.5 text-xs font-semibold text-jade-700">Nou</span>
                    Carduri de fidelitate digitale
                </p>
                <h1 class="mt-7 text-[2.75rem] font-semibold leading-[1.02] tracking-[-0.035em] text-ink sm:text-6xl lg:text-7xl">
                    Clienții revin.<br>
                    <span class="bg-gradient-to-r from-jade-600 via-jade-500 to-navy-500 bg-clip-text text-transparent">Afacerea crește.</span>
                </h1>
                <p class="mx-auto mt-6 max-w-xl text-balance text-base leading-relaxed text-ink-secondary sm:text-lg">
                    Ștampile din telefon pentru cafenele, restaurante, spălătorii și frizerii. Fără aplicație de instalat, fără cartonașe pierdute, fără ștampile „din milă”.
                </p>
                <div class="mt-9 flex flex-col items-center justify-center gap-3 sm:flex-row">
                    @auth
                        <x-ui.button :href="route('dashboard')" variant="brand" size="lg" pill>Mergi la dashboard <x-ui.icon name="arrow-right" class="h-5 w-5" /></x-ui.button>
                    @else
                        <x-ui.button :href="route('register')" variant="brand" size="lg" pill>Începe gratuit <x-ui.icon name="arrow-right" class="h-5 w-5" /></x-ui.button>
                    @endauth
                    <x-ui.button href="#cum-functioneaza" variant="light" size="lg" pill>Vezi cum funcționează</x-ui.button>
                </div>
            </div>

            {{-- Circuitul: decorativ. Impulsurile verzi curg din cip spre afaceri (oprite pentru prefers-reduced-motion). --}}
            <div class="relative mx-auto mt-10 aspect-[1000/380] w-full max-w-5xl sm:mt-6" aria-hidden="true">
                <svg class="absolute inset-0 h-full w-full" viewBox="0 0 1000 380" fill="none">
                    <defs>
                        <linearGradient id="circuit-line" x1="0" y1="0" x2="452" y2="0" gradientUnits="userSpaceOnUse">
                            <stop offset="0" stop-color="#B0C0DC" stop-opacity=".25"/>
                            <stop offset="1" stop-color="#8099C4"/>
                        </linearGradient>
                    </defs>
                    @foreach ([false, true] as $mirrored)
                        <g @if ($mirrored) transform="matrix(-1 0 0 1 1000 0)" @endif>
                            @foreach ($circuit as $line)
                                <path d="{{ $line['d'] }}" stroke="url(#circuit-line)" stroke-width="1.5"/>
                                <path d="{{ $line['d'] }}" pathLength="100" stroke="#01A760" stroke-width="2" stroke-linecap="round" stroke-dasharray="10 190" stroke-dashoffset="10"
                                      class="motion-safe:animate-flow" style="animation-delay: {{ $loop->index * 0.7 + ($mirrored ? 0.35 : 0) }}s"/>
                                @if (! $line['icons'])
                                    <circle cx="{{ $line['end'][0] }}" cy="{{ $line['end'][1] }}" r="4" fill="#5373A8" stroke="#fff" stroke-width="2"/>
                                @endif
                            @endforeach
                        </g>
                    @endforeach
                    @foreach ($vertical as $line)
                        <path d="{{ $line['d'] }}" stroke="#B0C0DC" stroke-width="1.5"/>
                        <circle cx="{{ $line['end'][0] }}" cy="{{ $line['end'][1] }}" r="3.5" fill="#8099C4" stroke="#fff" stroke-width="2"/>
                    @endforeach
                    {{-- Pinii cipului. --}}
                    @foreach ([470, 530] as $x)
                        <path d="M{{ $x }} 142 V132 M{{ $x }} 238 V248" stroke="#B0C0DC" stroke-width="1.5"/>
                    @endforeach
                </svg>

                {{-- Cipul central cu simbolul LoyUp. --}}
                <div class="absolute left-1/2 top-1/2 flex aspect-square w-[15%] -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-[22%] border border-white bg-white shadow-float sm:w-[9.6%]">
                    <div class="absolute inset-[9%] rounded-[18%] bg-gradient-to-b from-steel-50 to-white ring-1 ring-line"></div>
                    <x-brand.logo class="relative w-[52%] text-jade-500" />
                </div>

                {{-- Cipurile cu iconițe (stânga, apoi oglindite în dreapta). --}}
                @foreach ($circuit as $line)
                    @foreach ($line['icons'] ?? [] as $side => $icon)
                        @php($x = $side === 0 ? $line['end'][0] : 1000 - $line['end'][0])
                        <div class="absolute flex aspect-square w-[10%] -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-[28%] border border-white bg-white shadow-float sm:w-[5.4%]"
                             style="left: {{ $x / 10 }}%; top: {{ round($line['end'][1] / 3.8, 3) }}%">
                            <x-ui.icon :name="$icon" @class(['h-[46%] w-[46%]', 'text-jade-600' => ($loop->parent->index + $side) % 2 === 0, 'text-navy-600' => ($loop->parent->index + $side) % 2 === 1]) />
                        </div>
                    @endforeach
                @endforeach
            </div>
        </section>
    </div>

    {{-- 2. Rezultate: titlu la stânga, text la dreapta, patru carduri cu arce. --}}
    <section id="rezultate" class="scroll-mt-28 py-20 sm:py-28">
        <div class="mx-auto max-w-6xl px-5 sm:px-8">
            <div class="grid gap-6 lg:grid-cols-2 lg:items-end">
                <h2 class="text-4xl font-semibold leading-[1.08] tracking-[-0.03em] text-balance text-ink sm:text-5xl">Rezultate care se văd<br class="hidden sm:block"> la tejghea</h2>
                <p class="max-w-md text-ink-secondary lg:justify-self-end">Un program de fidelitate merge doar dacă e rapid pentru angajat, simplu pentru client și sigur pentru tine. LoyUp e construit în jurul acestor trei lucruri.</p>
            </div>

            <ul class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($results as $result)
                    @php([$ex, $ey] = $arcEnd($result['p']))
                    <li class="relative h-52 overflow-hidden rounded-3xl border border-line bg-steel-50 p-5">
                        <div class="flex items-start justify-between gap-3">
                            <p class="text-sm font-medium text-ink-secondary">{{ $result['label'] }}</p>
                            <x-ui.icon :name="$result['icon']" class="h-5 w-5 shrink-0 text-ink-muted" />
                        </div>
                        <p class="absolute bottom-5 left-5 text-4xl font-semibold tracking-tight text-ink">{{ $result['value'] }}</p>
                        <svg class="absolute bottom-0 right-0 h-36 w-36" viewBox="0 0 150 150" fill="none" aria-hidden="true">
                            <circle cx="130" cy="150" r="100" stroke="#D8E0EE" stroke-width="3"/>
                            <path d="M30 150 A100 100 0 0 1 130 50" pathLength="100" stroke="#01A760" stroke-width="3" stroke-linecap="round" stroke-dasharray="{{ $result['p'] * 100 }} 100"/>
                            <circle cx="{{ $ex }}" cy="{{ $ey }}" r="6" fill="#fff" stroke="#01A760" stroke-width="3"/>
                        </svg>
                        <p class="absolute bottom-5 right-5 text-sm font-semibold text-jade-700">{{ $result['note'] }}</p>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- 3. Cum funcționează: bento cu mini-interfețe. --}}
    <section id="cum-functioneaza" class="scroll-mt-28 pb-20 sm:pb-28">
        <div class="mx-auto max-w-6xl px-5 sm:px-8">
            <div class="mx-auto max-w-2xl text-center">
                <p class="inline-flex rounded-full border border-line bg-white px-3 py-1 text-xs font-semibold uppercase tracking-eyebrow text-jade-700 shadow-soft">Cum funcționează</p>
                <h2 class="mt-5 text-4xl font-semibold leading-[1.08] tracking-[-0.03em] text-balance text-ink sm:text-5xl">De la cartonaș la card digital, în trei pași</h2>
                <p class="mt-5 text-balance text-ink-secondary">Fără aplicații de instalat și fără hardware nou: telefonul angajatului e scannerul, telefonul clientului e cardul.</p>
            </div>

            <div class="mt-14 grid gap-4 lg:grid-cols-3">
                {{-- Pasul 1: configurarea. --}}
                <article class="flex flex-col rounded-3xl border border-line bg-gradient-to-b from-steel-50 to-white p-3">
                    <div class="relative flex h-56 flex-col justify-center overflow-hidden rounded-[1.25rem] border border-line bg-white bg-dots bg-[length:16px_16px] p-4" aria-hidden="true">
                        <div class="rounded-2xl border border-line bg-white p-3 shadow-soft">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-ink-muted">Template</p>
                            <div class="mt-1.5 flex items-center gap-2 rounded-xl border border-jade-200 bg-jade-50 px-3 py-2 text-sm font-semibold text-jade-800">
                                <x-ui.icon name="coffee" class="h-4 w-4" /> 5 cafele = 1 gratis
                                <x-ui.icon name="check" class="ml-auto h-4 w-4" />
                            </div>
                        </div>
                        <div class="mt-2.5 flex flex-wrap gap-2 text-xs font-semibold text-ink-secondary">
                            <span class="rounded-full border border-line bg-white px-3 py-1.5 shadow-soft">Cooldown 30 min</span>
                            <span class="rounded-full border border-line bg-white px-3 py-1.5 shadow-soft">Max 1 / vizită</span>
                        </div>
                        <div class="mt-2.5 flex items-center justify-between rounded-2xl border border-line bg-white px-3 py-2 shadow-soft">
                            <span class="text-sm font-medium text-ink-secondary">Ștampile</span>
                            <span class="flex items-center gap-3 text-sm font-semibold text-ink"><span class="flex h-6 w-6 items-center justify-center rounded-full bg-surface-muted">−</span>5<span class="flex h-6 w-6 items-center justify-center rounded-full bg-jade-600 text-white">+</span></span>
                        </div>
                    </div>
                    <div class="p-4 pt-5">
                        <p class="text-sm font-semibold text-jade-700">01</p>
                        <h3 class="mt-1 text-lg font-semibold text-ink">Îți configurezi programul</h3>
                        <p class="mt-2 text-sm leading-relaxed text-ink-secondary">Alegi un template sau faci regulile tale: ștampile, recompensă, expirare. Durează două minute.</p>
                    </div>
                </article>

                {{-- Pasul 2: cardul clientului (ștampilele se umplu pe rând, oprit pentru prefers-reduced-motion). --}}
                <article class="flex flex-col rounded-3xl border border-line bg-gradient-to-b from-steel-50 to-white p-3">
                    <div class="relative flex h-56 items-center justify-center overflow-hidden rounded-[1.25rem] border border-line bg-white bg-dots bg-[length:16px_16px] p-5" aria-hidden="true"
                         x-data="{
                             total: 5,
                             filled: 3,
                             timer: null,
                             init() {
                                 if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
                                 // Animația merge doar cât cardul e pe ecran.
                                 new IntersectionObserver(([entry]) => entry.isIntersecting ? this.start() : this.stop()).observe(this.$el);
                             },
                             start() {
                                 this.timer ??= setInterval(() => { this.filled = this.filled >= this.total ? 0 : this.filled + 1 }, 1400);
                             },
                             stop() {
                                 clearInterval(this.timer);
                                 this.timer = null;
                             },
                         }">
                        <div class="w-full max-w-[17rem] rounded-2xl bg-gradient-to-br from-navy-800 to-navy-950 p-4 text-white shadow-float">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2.5">
                                    <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-jade-500 text-sm font-bold text-navy-950">C</span>
                                    <div>
                                        <p class="text-sm font-semibold leading-tight">Cafenea Demo</p>
                                        <p class="text-[11px] text-navy-200">5 cafele = 1 gratis</p>
                                    </div>
                                </div>
                                <x-ui.icon name="qr" class="h-7 w-7 text-navy-200" />
                            </div>
                            {{-- Ștampilele sunt randate pe server, fiecare cu id-ul ei (gradientele din SVG nu se pot repeta). --}}
                            <div class="mt-4 grid grid-cols-5 gap-2">
                                @for ($i = 1; $i <= 5; $i++)
                                    <div class="relative flex aspect-square items-center justify-center rounded-full border-2 border-dashed border-navy-500">
                                        <div x-show="{{ $i }} <= filled" @if ($i > 3) style="display: none" @endif class="animate-stamp-in absolute -inset-0.5">
                                            <x-landing.seal :id="'bento-seal-'.$i" class="h-full w-full" />
                                        </div>
                                    </div>
                                @endfor
                            </div>
                            <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-navy-700">
                                <div class="h-full rounded-full bg-jade-500 transition-all duration-500" style="width: 60%" :style="{ width: (filled / total * 100) + '%' }"></div>
                            </div>
                            <p class="mt-2 text-xs font-semibold text-jade-300" x-text="filled >= total ? 'Cafea gratis!' : (total - filled) + ' până la cafeaua gratis'">2 până la cafeaua gratis</p>
                        </div>
                    </div>
                    <div class="p-4 pt-5">
                        <p class="text-sm font-semibold text-jade-700">02</p>
                        <h3 class="mt-1 text-lg font-semibold text-ink">Clientul își arată cardul</h3>
                        <p class="mt-2 text-sm leading-relaxed text-ink-secondary">Se înscrie cu emailul, fără parolă. Cardul îi arată progresul și un cod QR care se schimbă singur.</p>
                    </div>
                </article>

                {{-- Pasul 3: scanarea. --}}
                <article class="flex flex-col rounded-3xl border border-line bg-gradient-to-b from-steel-50 to-white p-3">
                    <div class="relative flex h-56 items-center justify-center overflow-hidden rounded-[1.25rem] border border-line bg-white bg-dots bg-[length:16px_16px] p-5" aria-hidden="true">
                        <div class="relative -mt-6 h-32 w-32">
                            @foreach (['left-0 top-0 border-l-[3px] border-t-[3px] rounded-tl-2xl', 'right-0 top-0 border-r-[3px] border-t-[3px] rounded-tr-2xl', 'bottom-0 left-0 border-b-[3px] border-l-[3px] rounded-bl-2xl', 'bottom-0 right-0 border-b-[3px] border-r-[3px] rounded-br-2xl'] as $corner)
                                <span class="absolute h-8 w-8 border-jade-500 {{ $corner }}"></span>
                            @endforeach
                            <x-ui.icon name="qr" class="absolute inset-5 h-[5.5rem] w-[5.5rem] text-navy-900" />
                            <span class="absolute inset-x-2 top-1/2 h-0.5 rounded-full bg-jade-500 shadow-[0_0_12px_2px_rgb(1_167_96/0.5)] motion-safe:animate-drift"></span>
                        </div>
                        <div class="absolute bottom-4 left-1/2 flex -translate-x-1/2 items-center gap-2 whitespace-nowrap rounded-full border border-line bg-white py-1.5 pl-1.5 pr-4 text-sm font-semibold text-ink shadow-float">
                            <span class="flex h-6 w-6 items-center justify-center rounded-full bg-jade-500 text-white"><x-ui.icon name="check" class="h-4 w-4" /></span>
                            +1 ștampilă · Ana
                        </div>
                    </div>
                    <div class="p-4 pt-5">
                        <p class="text-sm font-semibold text-jade-700">03</p>
                        <h3 class="mt-1 text-lg font-semibold text-ink">Angajatul scanează</h3>
                        <p class="mt-2 text-sm leading-relaxed text-ink-secondary">Scanare cu telefonul după plată sau confirmare dintr-un click. La ultima ștampilă, recompensa apare automat.</p>
                    </div>
                </article>

                {{-- Rapoarte (lat). Datele din grafic sunt un exemplu. --}}
                <article class="flex flex-col rounded-3xl border border-line bg-gradient-to-b from-steel-50 to-white p-3 lg:col-span-2">
                    <div class="relative h-56 overflow-hidden rounded-[1.25rem] border border-line bg-white p-5" aria-hidden="true">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-semibold text-ink">Ștampile pe zi</p>
                            <span class="rounded-full border border-line px-2.5 py-1 text-xs font-medium text-ink-secondary">Ultimele 7 zile</span>
                        </div>
                        <div class="mt-3 flex items-end gap-3 sm:gap-5">
                            @foreach ([['L', 46], ['M', 62], ['M', 40], ['J', 74], ['V', 92], ['S', 100], ['D', 58]] as [$day, $height])
                                <div class="flex flex-1 flex-col items-center gap-2">
                                    <div class="relative h-24 w-full max-w-10 rounded-full bg-steel-100">
                                        <div @class(['absolute inset-x-0 bottom-0 rounded-full', 'bg-gradient-to-t from-jade-600 to-jade-400' => $height === 100, 'bg-navy-200' => $height !== 100]) style="height: {{ $height }}%">
                                            <span @class(['absolute left-1/2 top-1.5 h-2 w-2 -translate-x-1/2 rounded-full', 'bg-white' => $height === 100, 'bg-navy-500' => $height !== 100])></span>
                                        </div>
                                    </div>
                                    <span class="text-xs font-medium text-ink-muted">{{ $day }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="flex items-end justify-between gap-4 p-4 pt-5">
                        <div>
                            <h3 class="text-lg font-semibold text-ink">Rapoarte pentru patron</h3>
                            <p class="mt-2 max-w-md text-sm leading-relaxed text-ink-secondary">Clienți activi, ștampile, recompense emise și folosite, pe locații și pe angajați.</p>
                        </div>
                        <x-ui.icon name="chart" class="hidden h-6 w-6 shrink-0 text-ink-muted sm:block" />
                    </div>
                </article>

                {{-- Jurnalul. --}}
                <article class="flex flex-col rounded-3xl border border-line bg-gradient-to-b from-steel-50 to-white p-3">
                    <div class="relative h-56 overflow-hidden rounded-[1.25rem] border border-line bg-white p-4" aria-hidden="true">
                        <ul class="space-y-2">
                            @foreach ([['check', 'Ștampilă · Ana P.', 'acum 2 min', '+1', 'jade'], ['gift', 'Recompensă · Bogdan I.', 'acum 9 min', '1 cafea', 'navy'], ['x', 'Anulare · scanare dublă', 'acum 15 min', '−1', 'steel']] as [$icon, $what, $when, $delta, $tone])
                                <li class="flex items-center gap-3 rounded-2xl border border-line bg-white p-2.5 shadow-soft">
                                    <span @class(['flex h-9 w-9 shrink-0 items-center justify-center rounded-xl', 'bg-jade-50 text-jade-700' => $tone === 'jade', 'bg-navy-50 text-navy-700' => $tone === 'navy', 'bg-steel-100 text-steel-600' => $tone === 'steel'])>
                                        <x-ui.icon :name="$icon" class="h-4 w-4" />
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-semibold text-ink">{{ $what }}</span>
                                        <span class="block text-xs text-ink-muted">{{ $when }}</span>
                                    </span>
                                    <span @class(['whitespace-nowrap text-sm font-semibold', 'text-jade-700' => $tone === 'jade', 'text-ink' => $tone === 'navy', 'text-ink-muted' => $tone === 'steel'])>{{ $delta }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                    <div class="p-4 pt-5">
                        <h3 class="text-lg font-semibold text-ink">Jurnal complet</h3>
                        <p class="mt-2 text-sm leading-relaxed text-ink-secondary">Cine, unde, când. Nimic nu se șterge: o greșeală se anulează cu o tranzacție inversă.</p>
                    </div>
                </article>
            </div>
        </div>
    </section>

    {{-- 4. Pentru cine. --}}
    <section id="pentru-cine" class="scroll-mt-28 pb-20 sm:pb-28">
        <div class="mx-auto max-w-6xl px-5 sm:px-8">
            <div class="grid gap-6 lg:grid-cols-2 lg:items-end">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-eyebrow text-jade-700">Pentru cine</p>
                    <h2 class="mt-4 text-4xl font-semibold leading-[1.08] tracking-[-0.03em] text-balance text-ink sm:text-5xl">Orice afacere în care clienții revin</h2>
                </div>
                <p class="max-w-md text-ink-secondary lg:justify-self-end">Pornești dintr-un template și îl ajustezi. Toate regulile se pot schimba oricând, fără să pierzi ștampilele clienților.</p>
            </div>

            <div class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($audiences as $audience)
                    <article class="flex flex-col rounded-3xl border border-line bg-white p-6 transition duration-300 hover:-translate-y-1 hover:shadow-float">
                        <div class="flex items-center justify-between">
                            <span @class(['flex h-12 w-12 items-center justify-center rounded-2xl', 'bg-jade-50 text-jade-700' => $loop->odd, 'bg-navy-50 text-navy-700' => $loop->even])>
                                <x-ui.icon :name="$audience['icon']" class="h-6 w-6" />
                            </span>
                            <span class="text-2xl font-semibold tracking-tight text-ink">{{ $audience['stamps'] }}<span class="text-ink-muted"> → </span>1</span>
                        </div>
                        <h3 class="mt-6 text-xl font-semibold text-ink">{{ $audience['title'] }}</h3>
                        <p class="mt-1 text-sm font-semibold text-jade-700">{{ $audience['stamps'] }} vizite → {{ $audience['reward'] }}</p>
                        <p class="mt-3 flex-1 text-sm leading-relaxed text-ink-secondary">{{ $audience['text'] }}</p>
                        <div class="mt-6 flex flex-col gap-2.5 border-t border-line pt-4">
                            <span class="text-xs font-medium text-ink-muted">{{ $audience['rule'] }}</span>
                            <span class="flex gap-1" aria-hidden="true">
                                @for ($i = 1; $i <= $audience['stamps']; $i++)
                                    <span @class(['h-2 w-2 rounded-full', 'bg-jade-500' => $i < $audience['stamps'], 'border border-dashed border-jade-500' => $i === $audience['stamps']])></span>
                                @endfor
                            </span>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    {{-- 5. Anti-fraudă: container pastel, codul QR cu numărătoarea inversă. --}}
    <div class="px-3 sm:px-5">
        <section id="anti-frauda" class="relative isolate scroll-mt-28 overflow-hidden rounded-[2rem] bg-hero py-20 sm:rounded-[2.5rem] sm:py-28">
            <div class="mx-auto max-w-6xl px-5 sm:px-8">
                <div class="grid items-center gap-12 lg:grid-cols-2">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-eyebrow text-jade-700">Anti-fraudă</p>
                        <h2 class="mt-4 text-4xl font-semibold leading-[1.08] tracking-[-0.03em] text-balance text-ink sm:text-5xl">Fiecare ștampilă e una adevărată</h2>
                        <p class="mt-5 max-w-lg text-ink-secondary">Programul de fidelitate trebuie să aducă clienți, nu pierderi. Regulile de protecție sunt incluse, nu opționale.</p>
                    </div>

                    {{-- Cardul cu QR: inelul arată cât mai e până când codul se schimbă. --}}
                    <div class="relative mx-auto w-full max-w-sm pb-6" aria-hidden="true">
                        <div class="rounded-[1.75rem] border border-white bg-white/80 p-6 shadow-float backdrop-blur">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-semibold text-ink">Cod pentru tejghea</p>
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-jade-50 px-2.5 py-1 text-xs font-semibold text-jade-700"><span class="h-1.5 w-1.5 rounded-full bg-jade-500"></span>Semnat</span>
                            </div>
                            <div class="mt-5 flex items-center gap-5">
                                <div class="relative h-28 w-28 shrink-0">
                                    <svg class="h-full w-full -rotate-90" viewBox="0 0 100 100" fill="none">
                                        <circle cx="50" cy="50" r="45" stroke="#E9EDF3" stroke-width="5"/>
                                        <circle cx="50" cy="50" r="45" pathLength="100" stroke="#01A760" stroke-width="5" stroke-linecap="round" stroke-dasharray="72 100"/>
                                    </svg>
                                    <x-ui.icon name="qr" class="absolute inset-7 h-14 w-14 text-navy-900" />
                                </div>
                                <div class="min-w-0">
                                    <p class="font-mono text-2xl font-bold tracking-[0.2em] text-ink">K7M-Q2X</p>
                                    <p class="mt-1 text-sm text-ink-secondary">Se schimbă în <span class="font-semibold text-ink">65 s</span></p>
                                    <p class="mt-3 inline-flex items-center gap-1.5 text-xs font-semibold text-ink-muted"><x-ui.icon name="shield" class="h-4 w-4" /> O singură folosire</p>
                                </div>
                            </div>
                        </div>
                        <div class="absolute -bottom-1 left-4 flex items-center gap-2 whitespace-nowrap rounded-full border border-line bg-white py-1.5 pl-1.5 pr-4 text-sm font-semibold text-ink shadow-float sm:-left-10">
                            <span class="flex h-6 w-6 items-center justify-center rounded-full bg-steel-100 text-steel-600"><x-ui.icon name="clock" class="h-4 w-4" /></span>
                            Următoarea ștampilă: peste 30 min
                        </div>
                    </div>
                </div>

                <ul class="mt-16 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($safeguards as $safeguard)
                        <li class="rounded-3xl border border-white bg-white/80 p-6 shadow-soft backdrop-blur">
                            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-navy-900 text-jade-400">
                                <x-ui.icon :name="$safeguard['icon']" class="h-5 w-5" />
                            </span>
                            <h3 class="mt-5 font-semibold text-ink">{{ $safeguard['title'] }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-ink-secondary">{{ $safeguard['text'] }}</p>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    </div>

    {{-- 6. Roluri: ce primește fiecare (în locul testimonialelor din referință, până există recenzii reale). --}}
    <section id="roluri" class="scroll-mt-28 py-20 sm:py-28">
        <div class="mx-auto max-w-6xl px-5 sm:px-8">
            <div class="mx-auto max-w-2xl text-center">
                <h2 class="text-4xl font-semibold leading-[1.08] tracking-[-0.03em] text-balance text-ink sm:text-5xl">Un singur sistem, trei roluri</h2>
                <p class="mt-5 text-balance text-ink-secondary">Fiecare vede doar ce are nevoie: patronul cifrele, angajatul scannerul, clientul cardul.</p>
            </div>

            <div class="mt-12 grid gap-4 lg:grid-cols-3">
                @foreach ($roles as $role)
                    <article class="flex flex-col rounded-3xl border border-line bg-steel-50 p-7">
                        <div class="flex items-center gap-3">
                            <span class="flex h-11 w-11 items-center justify-center rounded-full bg-white text-jade-700 shadow-soft">
                                <x-ui.icon :name="$role['icon']" class="h-5 w-5" />
                            </span>
                            <p class="text-sm font-semibold text-ink-secondary">{{ $role['role'] }}</p>
                        </div>
                        <h3 class="mt-6 text-xl font-semibold leading-snug text-ink">{{ $role['title'] }}</h3>
                        <ul class="mt-6 space-y-3 border-t border-line pt-6">
                            @foreach ($role['points'] as $point)
                                <li class="flex gap-3 text-sm text-ink-secondary">
                                    <x-ui.icon name="check" class="mt-0.5 h-4 w-4 shrink-0 text-jade-600" />
                                    {{ $point }}
                                </li>
                            @endforeach
                        </ul>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    {{-- 7. CTA final: containerul închis, cu lumina verde. --}}
    <div class="px-3 pb-3 sm:px-5 sm:pb-5">
        <section class="relative isolate overflow-hidden rounded-[2rem] bg-navy-950 bg-glow px-5 py-20 text-center sm:rounded-[2.5rem] sm:py-24">
            <div class="absolute inset-0 -z-10 bg-[radial-gradient(rgb(255_255_255/0.06)_1px,transparent_1px)] bg-[length:22px_22px] [mask-image:radial-gradient(ellipse_at_center,black_10%,transparent_70%)]" aria-hidden="true"></div>
            <x-brand.logo class="mx-auto h-12 w-14 text-jade-500" />
            <h2 class="mx-auto mt-8 max-w-2xl text-4xl font-semibold leading-[1.08] tracking-[-0.03em] text-balance text-white sm:text-5xl">Primul program e gata în câteva minute</h2>
            <p class="mx-auto mt-5 max-w-xl text-navy-200">Primii clienți îl folosesc chiar azi. Fără instalări, fără hardware, fără cartonașe.</p>
            <div class="mt-10 flex flex-col items-center justify-center gap-3 sm:flex-row">
                @auth
                    <x-ui.button :href="route('dashboard')" variant="brand" size="lg" pill>Mergi la dashboard <x-ui.icon name="arrow-right" class="h-5 w-5" /></x-ui.button>
                @else
                    <x-ui.button :href="route('register')" variant="brand" size="lg" pill>Creează-ți contul <x-ui.icon name="arrow-right" class="h-5 w-5" /></x-ui.button>
                    <x-ui.button :href="route('login')" variant="inverse" size="lg" pill>Am deja cont</x-ui.button>
                @endauth
            </div>
        </section>
    </div>
</x-marketing-layout>
