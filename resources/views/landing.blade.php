@php
    $marquee = ['Card digital', 'QR rotativ', 'Fără aplicație', 'Ștampile în 2 secunde', 'Anti-fraudă', 'Recompense automate', 'Pentru orice afacere'];

    // Exemplele vin din template-urile programelor (SDD §7); frizeria e un program personalizat.
    $audiences = [
        ['title' => 'Cafenele', 'image' => 'cafenea', 'stamps' => 5, 'reward' => '1 cafea gratis', 'rule' => 'Cooldown 30 min', 'text' => 'Clientul revine pentru a cincea cafea, nu pentru cafeneaua de vizavi.'],
        ['title' => 'Restaurante', 'image' => 'restaurant', 'stamps' => 8, 'reward' => 'Desert din partea casei', 'rule' => 'O ștampilă pe masă', 'text' => 'Fiecare masă contează, iar desertul gratuit aduce înapoi toată familia.'],
        ['title' => 'Spălătorii auto', 'image' => 'spalatorie', 'stamps' => 10, 'reward' => 'O spălare gratis', 'rule' => 'Maximum 1 pe zi', 'text' => 'Șoferii fideli sunt răsplătiți, fără carduri de carton uitate în torpedou.'],
        ['title' => 'Frizerii', 'image' => 'frizerie', 'stamps' => 6, 'reward' => 'O tunsoare gratis', 'rule' => 'Program personalizat', 'text' => 'Reguli pe care le alegi tu: câte ștampile, ce recompensă, cât e valabilă.'],
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
        {{-- Plantația de cafea văzută de sus (generată cu Figma AI); gradientul ține textul lizibil. --}}
        <img src="{{ asset('images/landing/hero-bg.webp') }}" alt="" width="1376" height="768" fetchpriority="high"
             class="absolute inset-0 -z-10 h-full w-full object-cover object-right opacity-80">
        <div class="absolute inset-0 -z-10 bg-gradient-to-r from-espresso-950 via-espresso-950/80 to-espresso-950/10" aria-hidden="true"></div>
        <div class="absolute inset-0 -z-10 bg-gradient-to-t from-espresso-950 via-transparent to-espresso-950/60" aria-hidden="true"></div>
        <div class="absolute inset-0 -z-10 bg-[radial-gradient(ellipse_at_70%_40%,rgba(204,153,51,0.25),transparent_55%)]" aria-hidden="true"></div>
        <div class="grain pointer-events-none absolute inset-0 -z-10 opacity-[0.07] mix-blend-overlay" aria-hidden="true"></div>

        <div class="mx-auto grid w-full max-w-7xl items-center gap-16 px-5 sm:px-8 lg:grid-cols-[1.15fr_1fr]">
            <div>
                <p class="inline-flex items-center gap-2 rounded-full border border-gold-500/40 bg-gold-500/10 px-4 py-1.5 text-xs font-semibold uppercase tracking-[0.2em] text-gold-300">
                    <span class="h-1.5 w-1.5 rounded-full bg-gold-400"></span>
                    Creative Loyalty
                </p>
                <h1 class="mt-8">
                    <span class="block -rotate-3 font-script text-7xl leading-[0.95] text-cream-50 sm:text-8xl lg:text-[9rem]">Fidelitate,</span>
                    <span class="relative mt-2 inline-block -rotate-3 pl-10 font-script text-6xl leading-none text-gold-400 sm:text-7xl lg:text-8xl">
                        nu hârtie.
                        <x-landing.squiggle class="absolute -bottom-5 left-8 w-[90%] text-gold-500" />
                    </span>
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
                <x-landing.rotating-badge class="absolute -left-3 -top-14 z-10 h-24 w-24 drop-shadow-xl sm:-left-16 sm:-top-12 sm:h-32 sm:w-32" />
                <x-landing.sparkle class="absolute -right-6 -top-4 h-7 w-7 text-gold-300 motion-safe:animate-twinkle" />
                <x-landing.sparkle class="absolute -bottom-8 -left-4 h-4 w-4 text-gold-400 motion-safe:animate-twinkle" style="animation-delay: 1.2s" />
                <x-landing.bean class="absolute -bottom-10 right-0 h-12 w-9 rotate-[30deg] text-gold-700/80 sm:-right-6" />
                <x-landing.bean class="absolute -right-12 top-1/3 hidden h-8 w-6 -rotate-12 text-gold-800/70 sm:block" />
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
                                <div x-show="i <= filled" class="animate-stamp-in absolute -inset-1 drop-shadow-[0_6px_10px_rgba(204,153,51,0.35)]">
                                    <x-landing.seal id="hero-seal" class="h-full w-full" />
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
                        <div class="relative flex aspect-square flex-col justify-between overflow-hidden rounded-3xl bg-gold-500 p-5 transition duration-300 group-hover:-translate-y-1 group-hover:shadow-2xl group-hover:shadow-gold-600/30">
                            {{-- Ilustrațiile 3D sunt generate cu Figma AI pe același fundal ca gold-500. --}}
                            <img src="{{ asset('images/landing/'.$audience['image'].'.webp') }}" alt="" width="800" height="800" loading="lazy"
                                 class="absolute inset-0 h-full w-full object-cover transition duration-500 group-hover:scale-105">
                            <div class="grain pointer-events-none absolute inset-0 opacity-20 mix-blend-multiply" aria-hidden="true"></div>
                            <p class="relative text-5xl font-extrabold leading-none tracking-tighter text-espresso-950">{{ $audience['stamps'] }}<span class="text-2xl">→1</span></p>
                            <div class="relative flex items-center justify-between gap-3 rounded-2xl bg-espresso-950/85 px-3 py-2.5 backdrop-blur-sm">
                                <span class="whitespace-nowrap text-xs font-semibold text-gold-300">{{ $audience['rule'] }}</span>
                                <span class="flex flex-wrap justify-end gap-1" aria-hidden="true">
                                    @for ($i = 1; $i <= $audience['stamps']; $i++)
                                        <span class="h-1.5 w-1.5 rounded-full {{ $i < $audience['stamps'] ? 'bg-gold-400' : 'border border-dashed border-gold-400' }}"></span>
                                    @endfor
                                </span>
                            </div>
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
                    {{-- Fundalul imaginii e espresso-900; masca radială o topește în secțiune. --}}
                    <img src="{{ asset('images/landing/anti-frauda.webp') }}" alt="" width="800" height="800" loading="lazy"
                         class="mt-6 w-64 [mask-image:radial-gradient(circle,black_50%,transparent_70%)] motion-safe:animate-float sm:w-80">
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
    <section class="relative isolate overflow-hidden bg-gold-500 py-20 text-espresso-950 lg:py-12">
        {{-- Marginea perforată, ca la un card de carton rupt din carnet. --}}
        <div class="absolute inset-x-0 top-0 h-3 bg-[radial-gradient(circle_at_12px_0,theme(colors.espresso.900)_10px,transparent_10.5px)] bg-[length:24px_12px] bg-repeat-x" aria-hidden="true"></div>
        <div class="grain pointer-events-none absolute inset-0 -z-10 opacity-20 mix-blend-multiply" aria-hidden="true"></div>
        <div class="mx-auto grid max-w-7xl items-center gap-6 px-5 sm:px-8 lg:grid-cols-[1fr_auto]">
            <div>
                <p class="relative inline-block -rotate-2 font-script text-6xl leading-none sm:text-7xl">
                    Clienții revin.
                    <x-landing.sparkle class="absolute -right-8 -top-4 h-6 w-6 text-cream-50 motion-safe:animate-twinkle" />
                </p>
                <p class="mt-6 max-w-xl text-lg font-medium">Primul program e gata în câteva minute. Primii clienți îl folosesc chiar azi.</p>
                <div class="mt-10">
                    @auth
                        <x-ui.button :href="route('dashboard')" variant="neutral" size="xl">Mergi la dashboard <x-ui.icon name="arrow-right" class="h-5 w-5" /></x-ui.button>
                    @else
                        <x-ui.button :href="route('register')" variant="neutral" size="xl">Creează-ți contul <x-ui.icon name="arrow-right" class="h-5 w-5" /></x-ui.button>
                    @endauth
                </div>
            </div>
            {{-- Fundalul imaginii e gold-500; masca radială ascunde marginile. --}}
            <img src="{{ asset('images/landing/cta-phone.webp') }}" alt="" width="900" height="900" loading="lazy"
                 class="mx-auto w-72 [mask-image:radial-gradient(circle,black_55%,transparent_72%)] sm:w-96 lg:w-[28rem]">
        </div>
    </section>
</x-marketing-layout>
