@php
    $marquee = ['Card digital', 'QR rotativ', 'Fără aplicație', 'Ștampile în 2 secunde', 'Anti-fraudă', 'Recompense automate', 'Pentru orice afacere'];

    // Exemplele vin din template-urile programelor (SDD §7); frizeria e un program personalizat.
    $audiences = [
        ['title' => 'Cafenele', 'stamps' => 5, 'reward' => '1 cafea gratis', 'rule' => 'Cooldown 30 min', 'text' => 'Clientul revine pentru a cincea cafea, nu pentru cafeneaua de vizavi.'],
        ['title' => 'Restaurante', 'stamps' => 8, 'reward' => 'Desert din partea casei', 'rule' => 'O ștampilă pe masă', 'text' => 'Fiecare masă contează, iar desertul gratuit aduce înapoi toată familia.'],
        ['title' => 'Spălătorii auto', 'stamps' => 10, 'reward' => 'O spălare gratis', 'rule' => 'Maximum 1 pe zi', 'text' => 'Șoferii fideli sunt răsplătiți, fără carduri de carton uitate în torpedou.'],
        ['title' => 'Frizerii', 'stamps' => 6, 'reward' => 'O tunsoare gratis', 'rule' => 'Program personalizat', 'text' => 'Reguli pe care le alegi tu: câte ștampile, ce recompensă, cât e valabilă.'],
    ];

    $steps = [
        ['icon' => 'sliders', 'title' => 'Îți configurezi programul', 'text' => 'Alegi un template („5 cafele = 1 gratis”) sau faci regulile tale: ștampile, recompensă, expirare. Durează două minute.'],
        ['icon' => 'phone', 'title' => 'Clientul își arată cardul', 'text' => 'Se înscrie cu emailul, fără aplicație și fără parolă. Cardul digital îi arată progresul și un cod QR care se schimbă singur.'],
        ['icon' => 'qr', 'title' => 'Angajatul scanează', 'text' => 'Scanare cu telefonul după plată sau confirmare dintr-un click. La ultima ștampilă, recompensa apare automat.'],
    ];

    $safeguards = [
        ['icon' => 'qr', 'title' => 'QR semnat, valabil 90 de secunde', 'text' => 'Codul de pe card se schimbă singur și poate fi folosit o singură dată. Un screenshot vechi nu mai funcționează.'],
        ['icon' => 'clock', 'title' => 'Cooldown și limită zilnică', 'text' => 'Tu decizi câte ștampile pe vizită și cât timp trebuie să treacă între ele. Regulile se aplică automat.'],
        ['icon' => 'shield', 'title' => 'Un click, o singură ștampilă', 'text' => 'Dublu-click, internet care cade, butonul apăsat de două ori? Ștampila se adaugă o singură dată.'],
        ['icon' => 'list', 'title' => 'Jurnal complet', 'text' => 'Vezi cine a dat fiecare ștampilă, unde și când. Greșelile se anulează transparent, nimic nu se șterge.'],
    ];
@endphp

<x-marketing-layout title="Carduri de fidelitate digitale" description="Creative Loyalty: carduri de fidelitate digitale cu ștampile și QR pentru cafenele, restaurante, spălătorii și frizerii.">
    {{-- 1. Hero --}}
    <section class="relative isolate flex min-h-[100svh] items-center overflow-hidden bg-espresso-950 pb-16 pt-28 text-cream-50">
        <div class="absolute inset-0 -z-10 bg-[radial-gradient(ellipse_at_70%_30%,rgba(204,153,51,0.35),transparent_55%),radial-gradient(ellipse_at_10%_90%,rgba(204,153,51,0.18),transparent_50%)]" aria-hidden="true"></div>
        {{-- Rânduri curbe, ca o plantație văzută de sus. --}}
        <svg class="absolute inset-0 -z-10 h-full w-full text-gold-500/10" viewBox="0 0 1440 900" preserveAspectRatio="xMidYMid slice" fill="none" aria-hidden="true">
            @for ($i = 0; $i < 14; $i++)
                <path d="M{{ -200 + $i * 60 }} 900 C {{ 300 + $i * 70 }} {{ 620 - $i * 18 }}, {{ 700 + $i * 50 }} {{ 420 - $i * 22 }}, {{ 1640 }} {{ 180 - $i * 26 }}" stroke="currentColor" stroke-width="{{ 18 - $i }}" stroke-linecap="round"/>
            @endfor
        </svg>
        <div class="grain pointer-events-none absolute inset-0 -z-10 opacity-[0.07] mix-blend-overlay" aria-hidden="true"></div>

        <div class="mx-auto grid w-full max-w-7xl items-center gap-16 px-5 sm:px-8 lg:grid-cols-[1.15fr_1fr]">
            <div>
                <p class="inline-flex items-center gap-2 rounded-full border border-gold-500/40 bg-gold-500/10 px-4 py-1.5 text-xs font-semibold uppercase tracking-[0.2em] text-gold-300">
                    <span class="h-1.5 w-1.5 rounded-full bg-gold-400"></span>
                    Creative Loyalty
                </p>
                <h1 class="mt-8">
                    <span class="block -rotate-3 font-script text-7xl leading-[0.95] text-cream-50 sm:text-8xl lg:text-[9rem]">Fidelitate,</span>
                    <span class="mt-2 block -rotate-3 pl-10 font-script text-6xl leading-none text-gold-400 sm:text-7xl lg:text-8xl">nu hârtie.</span>
                </h1>
                <p class="mt-10 max-w-xl text-lg font-medium leading-relaxed text-cream-100/90 sm:text-xl">
                    Carduri de fidelitate digitale pentru afacerea ta. Clienții adună ștampile din telefon, iar tu îi vezi revenind.
                </p>
                <div class="mt-10 flex flex-wrap items-center gap-4">
                    @auth
                        <x-ui.button :href="route('dashboard')" size="lg">Mergi la dashboard <x-ui.icon name="arrow-right" class="h-5 w-5" /></x-ui.button>
                    @else
                        <x-ui.button :href="route('register')" size="lg">Începe gratuit <x-ui.icon name="arrow-right" class="h-5 w-5" /></x-ui.button>
                    @endauth
                    <x-ui.button href="#cum-functioneaza" variant="inverse" size="lg">Vezi cum funcționează</x-ui.button>
                </div>
            </div>

            {{-- Mockup-ul cardului: ștampilele se umplu pe rând (oprit pentru prefers-reduced-motion). --}}
            <div class="relative mx-auto w-full max-w-sm"
                 x-data="{
                     total: 5,
                     filled: 3,
                     init() {
                         if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
                         setInterval(() => { this.filled = this.filled >= this.total ? 0 : this.filled + 1 }, 1400);
                     },
                 }">
                <div class="absolute -inset-8 -z-10 rounded-full bg-gold-500/20 blur-3xl" aria-hidden="true"></div>
                <div class="animate-float rounded-4xl border border-gold-500/30 bg-gradient-to-br from-espresso-800 to-espresso-950 p-7 shadow-2xl shadow-black/50">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-gold-500 text-lg font-bold text-espresso-950">C</span>
                            <div>
                                <p class="font-semibold">Cafenea Demo</p>
                                <p class="text-xs text-espresso-300">5 cafele = 1 gratis</p>
                            </div>
                        </div>
                        <x-brand.logo class="h-8 w-8 text-gold-500/60" />
                    </div>

                    <div class="mt-8 grid grid-cols-5 gap-3" role="img" aria-label="Card de fidelitate cu 3 din 5 ștampile">
                        <template x-for="i in total" :key="i">
                            <div class="relative flex aspect-square items-center justify-center rounded-full border-2 border-dashed border-espresso-500">
                                {{-- Animația pornește din nou de fiecare dată când `x-show` afișează ștampila. --}}
                                <div x-show="i <= filled"
                                     class="animate-stamp-in absolute inset-0 flex items-center justify-center rounded-full bg-gold-500 text-espresso-950 shadow-lg shadow-gold-500/30">
                                    <x-brand.logo class="h-6 w-6" />
                                </div>
                            </div>
                        </template>
                    </div>

                    <div class="mt-6 flex items-end justify-between">
                        <p class="text-4xl font-bold tracking-tight"><span x-text="filled">3</span><span class="text-xl font-semibold text-espresso-300"> din <span x-text="total">5</span></span></p>
                        <p class="text-sm font-semibold text-gold-400" x-text="filled >= total ? 'Cafea gratis! ☕' : (total - filled) + ' până la cafeaua gratis'">2 până la cafeaua gratis</p>
                    </div>
                    <div class="mt-4 h-2 overflow-hidden rounded-full bg-espresso-700">
                        <div class="h-full rounded-full bg-gold-500 transition-all duration-500" style="width: 60%" :style="{ width: (filled / total * 100) + '%' }"></div>
                    </div>

                    <div class="mt-7 flex items-center gap-4 rounded-2xl bg-cream-50 p-4 text-espresso-900">
                        <x-ui.icon name="qr" class="h-14 w-14 shrink-0" />
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wider text-espresso-500">Cod pentru tejghea</p>
                            <p class="font-mono text-2xl font-bold tracking-[0.25em]">K7M-Q2X</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <a href="#cum-functioneaza" class="absolute bottom-6 left-1/2 -translate-x-1/2 text-cream-50/70 transition hover:text-gold-400" aria-label="Mergi mai jos">
            <x-ui.icon name="chevron-down" class="h-8 w-8 motion-safe:animate-bounce" />
        </a>
    </section>

    {{-- 2. Bandă marquee aurie --}}
    <div class="overflow-hidden bg-gold-500 py-5 text-espresso-950" aria-hidden="true">
        <div class="flex w-max animate-marquee">
            @foreach ([0, 1] as $copy)
                <div class="flex shrink-0 items-center">
                    @foreach ($marquee as $item)
                        <span class="px-8 text-2xl font-extrabold uppercase tracking-tight sm:text-3xl">{{ $item }}</span>
                        <x-brand.logo class="h-7 w-7 shrink-0" />
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>

    {{-- 3. Cum funcționează --}}
    <section id="cum-functioneaza" class="scroll-mt-20 bg-cream-50 py-24 sm:py-32">
        <div class="mx-auto max-w-7xl px-5 sm:px-8">
            <div class="max-w-2xl">
                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-gold-600">Cum funcționează</p>
                <h2 class="mt-4 text-4xl font-bold tracking-tight text-espresso-900 sm:text-5xl">Trei pași. Zero hârtie.</h2>
                <p class="mt-5 text-lg leading-relaxed text-espresso-600">Fără aplicații de instalat, fără carduri de carton pierdute, fără ștampile puse „din milă”.</p>
            </div>

            <ol class="mt-16 grid gap-6 md:grid-cols-3">
                @foreach ($steps as $step)
                    <li class="group relative overflow-hidden rounded-3xl border border-espresso-100 bg-white p-8 transition hover:-translate-y-1 hover:shadow-xl hover:shadow-espresso-900/5">
                        <span class="absolute -right-3 -top-6 text-[9rem] font-extrabold leading-none text-cream-200 transition group-hover:text-gold-100" aria-hidden="true">{{ $loop->iteration }}</span>
                        <span class="relative flex h-14 w-14 items-center justify-center rounded-2xl bg-espresso-900 text-gold-400">
                            <x-ui.icon :name="$step['icon']" class="h-7 w-7" />
                        </span>
                        <h3 class="relative mt-8 text-xl font-bold text-espresso-900">{{ $step['title'] }}</h3>
                        <p class="relative mt-3 leading-relaxed text-espresso-600">{{ $step['text'] }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- 4. Pentru cine --}}
    <section id="pentru-cine" class="scroll-mt-20 bg-white py-24 sm:py-32">
        <div class="mx-auto max-w-7xl px-5 sm:px-8">
            <div class="flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
                <div class="max-w-2xl">
                    <p class="text-sm font-semibold uppercase tracking-[0.2em] text-gold-600">Pentru cine</p>
                    <h2 class="mt-4 text-4xl font-bold tracking-tight text-espresso-900 sm:text-5xl">Orice afacere în care clienții revin.</h2>
                </div>
                <p class="max-w-sm text-espresso-600">Pornești dintr-un template și îl ajustezi. Toate regulile se pot schimba oricând.</p>
            </div>

            <div class="mt-16 grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($audiences as $audience)
                    <article class="group flex flex-col">
                        <div class="relative flex aspect-square flex-col justify-between overflow-hidden rounded-3xl bg-gradient-to-br from-gold-400 to-gold-600 p-6 transition duration-300 group-hover:-translate-y-1 group-hover:shadow-2xl group-hover:shadow-gold-600/30">
                            <div class="grain pointer-events-none absolute inset-0 opacity-20 mix-blend-multiply" aria-hidden="true"></div>
                            <p class="relative text-6xl font-extrabold leading-none tracking-tighter text-espresso-950">{{ $audience['stamps'] }}<span class="text-3xl">→1</span></p>
                            <div class="relative grid grid-cols-5 gap-2" aria-hidden="true">
                                @for ($i = 1; $i <= $audience['stamps']; $i++)
                                    <span class="aspect-square rounded-full {{ $i < $audience['stamps'] ? 'bg-espresso-950' : 'border-2 border-dashed border-espresso-950/60' }}"></span>
                                @endfor
                            </div>
                            <span class="relative inline-flex w-fit rounded-full bg-espresso-950 px-3 py-1 text-xs font-semibold text-gold-300">{{ $audience['rule'] }}</span>
                        </div>
                        <h3 class="mt-6 text-2xl font-bold text-gold-600">{{ $audience['title'] }}</h3>
                        <p class="mt-1 font-semibold text-espresso-900">{{ $audience['stamps'] }} vizite → {{ $audience['reward'] }}</p>
                        <p class="mt-3 leading-relaxed text-espresso-600">{{ $audience['text'] }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    {{-- 5. Anti-fraudă --}}
    <section id="anti-frauda" class="relative isolate scroll-mt-20 overflow-hidden bg-espresso-900 py-24 text-cream-50 sm:py-32">
        <div class="absolute inset-0 -z-10 bg-[radial-gradient(ellipse_at_top_right,rgba(204,153,51,0.18),transparent_60%)]" aria-hidden="true"></div>
        <div class="mx-auto max-w-7xl px-5 sm:px-8">
            <div class="grid gap-16 lg:grid-cols-[1fr_1.4fr]">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.2em] text-gold-400">Anti-fraudă</p>
                    <h2 class="mt-4 text-4xl font-bold tracking-tight sm:text-5xl">Fiecare ștampilă e una adevărată.</h2>
                    <p class="mt-5 text-lg leading-relaxed text-espresso-200">Programul de fidelitate trebuie să aducă clienți, nu pierderi. Regulile de protecție sunt incluse, nu opționale.</p>
                    <p class="mt-10 font-script text-5xl text-gold-400">Fără ștampile „din milă”.</p>
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    @foreach ($safeguards as $safeguard)
                        <div class="rounded-3xl border border-espresso-700 bg-espresso-800/50 p-7 transition hover:border-gold-500/50">
                            <x-ui.icon :name="$safeguard['icon']" class="h-8 w-8 text-gold-400" />
                            <h3 class="mt-6 text-lg font-bold">{{ $safeguard['title'] }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-espresso-200">{{ $safeguard['text'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- 6. CTA final --}}
    <section class="relative isolate overflow-hidden bg-gold-500 py-24 text-espresso-950">
        <div class="grain pointer-events-none absolute inset-0 -z-10 opacity-20 mix-blend-multiply" aria-hidden="true"></div>
        <div class="mx-auto flex max-w-7xl flex-col items-start gap-10 px-5 sm:px-8 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="-rotate-2 font-script text-6xl leading-none sm:text-7xl">Clienții revin.</p>
                <p class="mt-6 max-w-xl text-lg font-medium">Primul program e gata în câteva minute. Primii clienți îl folosesc chiar azi.</p>
            </div>
            @auth
                <x-ui.button :href="route('dashboard')" variant="neutral" size="xl">Mergi la dashboard <x-ui.icon name="arrow-right" class="h-5 w-5" /></x-ui.button>
            @else
                <x-ui.button :href="route('register')" variant="neutral" size="xl">Creează-ți contul <x-ui.icon name="arrow-right" class="h-5 w-5" /></x-ui.button>
            @endauth
        </div>
    </section>
</x-marketing-layout>
