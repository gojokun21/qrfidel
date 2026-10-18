@php
    // Landing-ul urmează Figma „Landing v3” (ADR 019). Animațiile (resources/js/landing.js) folosesc atributele data-anim;
    // fără JavaScript sau cu prefers-reduced-motion, pagina arată exact ca designul static.

    // Cifrele sunt fapte despre produs (SDD), nu statistici inventate. `count` = valoarea de la care numără animația.
    $facts = [
        ['value' => '2 min', 'count' => 2, 'prefix' => '', 'suffix' => ' min', 'label' => 'Configurarea unui program', 'note' => 'din template'],
        ['value' => '~2 s', 'count' => 2, 'prefix' => '~', 'suffix' => ' s', 'label' => 'O ștampilă la tejghea', 'note' => 'scanare cu telefonul'],
        ['value' => '90 s', 'count' => 90, 'prefix' => '', 'suffix' => ' s', 'label' => 'Valabilitatea codului QR', 'note' => 'se schimbă singur'],
        ['value' => '100%', 'count' => 100, 'prefix' => '', 'suffix' => '%', 'label' => 'Tranzacții în jurnal', 'note' => 'nimic nu se șterge'],
    ];

    $steps = [
        ['title' => 'Îți configurezi programul', 'text' => 'Alegi un template sau faci regulile tale: ștampile, recompensă, expirare. Durează două minute.'],
        ['title' => 'Clientul își arată cardul', 'text' => 'Se înscrie cu emailul, fără parolă. Cardul îi arată progresul și un cod QR care se schimbă singur.'],
        ['title' => 'Angajatul scanează', 'text' => 'Scanare cu telefonul după plată sau confirmare dintr-un click. La ultima ștampilă, recompensa apare automat.'],
    ];
    // Ca în design, pasul activ fără JavaScript e ultimul; animația îi parcurge pe rând.
    $activeStep = count($steps) - 1;

    // Exemplele vin din template-urile programelor (SDD §7); frizeria e un program personalizat.
    $audiences = [
        ['title' => 'Cafenele', 'photo' => 'cafenele', 'reward' => '5 vizite → 1 cafea gratis', 'rule' => 'Cooldown 30 min', 'text' => 'Clientul revine pentru a cincea cafea, nu pentru cafeneaua de vizavi.'],
        ['title' => 'Restaurante', 'photo' => 'restaurante', 'reward' => '8 vizite → desert din partea casei', 'rule' => 'O ștampilă pe masă', 'text' => 'Fiecare masă contează, iar desertul gratuit aduce înapoi toată familia.'],
        ['title' => 'Spălătorii auto', 'photo' => 'spalatorii', 'reward' => '10 vizite → o spălare gratis', 'rule' => 'Maximum 1 pe zi', 'text' => 'Șoferii fideli sunt răsplătiți, fără carduri de carton uitate în torpedou.'],
        ['title' => 'Frizerii', 'photo' => 'frizerii', 'reward' => '6 vizite → o tunsoare gratis', 'rule' => 'Program personalizat', 'text' => 'Reguli pe care le alegi tu: câte ștampile, ce recompensă, cât e valabilă.'],
    ];

    $safeguards = [
        ['icon' => 'shield-check', 'title' => 'QR semnat, valabil 90 de secunde', 'text' => 'Codul se schimbă singur și merge o singură dată. Un screenshot vechi nu mai funcționează.'],
        ['icon' => 'clock', 'title' => 'Cooldown și limită zilnică', 'text' => 'Tu decizi câte ștampile pe vizită și cât timp trece între ele.'],
        ['icon' => 'click', 'title' => 'Un click, o singură ștampilă', 'text' => 'Dublu-click sau internet care cade? Ștampila se adaugă o singură dată.'],
        ['icon' => 'list-lines', 'title' => 'Jurnal complet', 'text' => 'Cine, unde, când. Greșelile se anulează transparent, nimic nu se șterge.'],
    ];

    $roles = [
        ['role' => 'Pentru patron', 'icon' => 'bar-chart', 'text' => 'Vezi cine revine și ce aduce programul.', 'chips' => ['Programe din template', 'Rapoarte pe locații', 'Echipă cu roluri']],
        ['role' => 'Pentru angajat', 'icon' => 'scan-frame', 'text' => 'O ștampilă în două secunde, fără greșeli.', 'chips' => ['Scanare cu camera', 'Confirmare dintr-un click', 'Anulare cu motiv']],
        ['role' => 'Pentru client', 'icon' => 'smartphone', 'text' => 'Cardul e mereu în telefon.', 'chips' => ['Intră cu emailul', 'Fără parolă', 'Se instalează ca aplicație']],
    ];

    $img = fn (string $name): string => asset('images/marketing/'.$name.'.webp');
@endphp

<x-marketing-layout title="Carduri de fidelitate digitale" description="LoyUp: carduri de fidelitate digitale cu ștampile și QR pentru cafenele, restaurante, spălătorii și frizerii.">
    {{-- 1. Hero: containerul deschis la culoare (meniul din layout stă peste el), textul în stânga, fotografia în dreapta. --}}
    <div class="px-3 pt-3 lg:px-4 lg:pt-4">
        <section class="overflow-hidden rounded-[2rem] bg-[#F2F6F8] px-5 pb-12 pt-32 sm:px-8 lg:px-12 lg:pb-[5.5rem] lg:pt-[9.625rem]">
            <div class="mx-auto flex max-w-[82rem] flex-col gap-12 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex max-w-[36.25rem] flex-col items-start gap-7">
                    <p data-anim="fade" class="inline-flex items-center gap-2.5 rounded-full border border-steel-100 bg-white py-[0.4375rem] pl-2 pr-3.5 text-[0.8125rem] font-medium text-steel-600">
                        <span class="rounded-full bg-jade-50 px-[0.5625rem] py-0.5 text-xs font-semibold text-jade-700">Nou</span>
                        Carduri de fidelitate digitale
                    </p>
                    <h1 class="text-[2.75rem] font-semibold leading-[1.02] tracking-[-0.035em] text-ink sm:text-6xl lg:text-[4.5rem]">
                        <span class="block overflow-hidden pb-[0.06em]"><span data-anim="line" class="block">Clienții revin.</span></span>
                        <span class="block overflow-hidden pb-[0.06em]"><span data-anim="line" class="block text-jade-600">Afacerea crește.</span></span>
                    </h1>
                    <p data-anim="fade" class="max-w-[32.5rem] text-lg leading-[1.55] text-steel-600 lg:text-[1.1875rem]">
                        Ștampile din telefon pentru cafenele, restaurante, spălătorii și frizerii. Fără aplicație de instalat, fără cartonașe pierdute, fără ștampile „din milă”.
                    </p>
                    <div data-anim="fade" class="flex flex-wrap gap-3">
                        @auth
                            <x-marketing.button :href="route('dashboard')" arrow>Mergi la dashboard</x-marketing.button>
                        @else
                            <x-marketing.button :href="route('register')" arrow>Începe gratuit</x-marketing.button>
                        @endauth
                        <x-marketing.button href="#cum-functioneaza" variant="light">Vezi cum funcționează</x-marketing.button>
                    </div>
                </div>

                <div class="relative mx-auto aspect-[470/620] w-full max-w-[29.375rem] overflow-hidden rounded-[1.75rem] lg:mx-0 lg:shrink-0">
                    <img data-anim="parallax" src="{{ $img('hero-barista') }}" alt="Barista care îi întinde unui client cafeaua la tejghea" width="960" height="1440" fetchpriority="high"
                         class="absolute inset-0 h-full w-full scale-110 object-cover">
                </div>
            </div>
        </section>
    </div>

    {{-- 2. Cifre: patru fapte despre produs, despărțite de linii verticale. --}}
    <section id="rezultate" class="scroll-mt-28 px-5 pb-2 pt-16 sm:px-8 lg:px-[7.5rem] lg:pt-[4.5rem]" aria-label="Pe scurt">
        <dl class="mx-auto grid max-w-[75rem] grid-cols-2 gap-y-10 lg:grid-cols-4">
            @foreach ($facts as $fact)
                <div @class(['flex flex-col gap-1.5 py-1', 'pr-8' => $loop->first, 'border-l border-steel-200 px-5 sm:px-8' => ! $loop->first, 'max-lg:border-l-0 max-lg:pl-0' => $loop->index === 2])>
                    <dt class="order-2 text-[0.9375rem] font-semibold leading-[1.55] text-ink">{{ $fact['label'] }}</dt>
                    <dd class="order-1 text-4xl font-semibold leading-[1.1] tracking-[-0.03em] text-ink lg:text-[2.75rem]"
                        data-anim="count" data-count="{{ $fact['count'] }}" data-prefix="{{ $fact['prefix'] }}" data-suffix="{{ $fact['suffix'] }}">{{ $fact['value'] }}</dd>
                    <dd class="order-3 text-sm text-steel-500">{{ $fact['note'] }}</dd>
                </div>
            @endforeach
        </dl>
    </section>

    {{-- 3. Cum funcționează: fotografia în stânga, pașii în dreapta; pe desktop secțiunea se fixează și pașii devin activi pe rând. --}}
    <section id="cum-functioneaza" data-anim="steps" class="scroll-mt-28 px-5 py-20 sm:px-8 lg:px-[7.5rem] lg:py-28">
        <div class="mx-auto max-w-[75rem]">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <h2 class="max-w-[40rem] text-4xl font-semibold leading-[1.08] tracking-[-0.025em] text-ink lg:text-[2.875rem]">De la cartonaș la card digital, în trei pași</h2>
                <p class="max-w-[26.25rem] text-[1.0625rem] leading-relaxed text-steel-600">Fără aplicații de instalat și fără hardware nou: telefonul angajatului e scannerul, telefonul clientului e cardul.</p>
            </div>

            <div class="mt-12 flex flex-col gap-10 lg:mt-14 xl:flex-row xl:items-center xl:gap-16">
                <div class="aspect-[640/580] w-full overflow-hidden rounded-[1.75rem] xl:w-[40rem] xl:shrink-0">
                    <img src="{{ $img('scan-counter') }}" alt="Angajatul scanează cu telefonul cardul de pe telefonul clientului" width="1280" height="853" loading="lazy" decoding="async"
                         class="h-full w-full object-cover">
                </div>

                <div class="flex flex-1 flex-col gap-3">
                    <ol class="flex flex-col gap-3">
                        @foreach ($steps as $step)
                            <li data-step data-active="{{ $loop->index === $activeStep ? 'true' : 'false' }}"
                                class="group/step flex gap-5 rounded-[1.25rem] border border-transparent p-6 transition duration-300 data-[active=true]:border-steel-100 data-[active=true]:bg-white data-[active=true]:shadow-[0_8px_12px_rgb(1_33_86/0.08),0_1px_1px_rgb(1_33_86/0.04)]">
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-steel-50 text-sm font-semibold text-steel-500 transition-colors duration-300 group-data-[active=true]/step:bg-jade-600 group-data-[active=true]/step:text-white">{{ sprintf('%02d', $loop->iteration) }}</span>
                                <div class="flex flex-col gap-1.5">
                                    <h3 class="text-xl font-semibold leading-[1.55] tracking-[-0.01em] text-ink/55 transition-colors duration-300 group-data-[active=true]/step:text-ink">{{ $step['title'] }}</h3>
                                    <p class="text-[0.9375rem] leading-relaxed text-steel-600/70 transition-colors duration-300 group-data-[active=true]/step:text-steel-600">{{ $step['text'] }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                    <div class="flex gap-2 pl-6 pt-2" aria-hidden="true">
                        @foreach ($steps as $step)
                            <span data-step-bar data-active="{{ $loop->index === $activeStep ? 'true' : 'false' }}" class="h-1 w-4 rounded-sm bg-jade-600 transition-[width] duration-300 data-[active=true]:w-10"></span>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- 4. Pentru cine: panoul gri, patru carduri cu fotografie, regula pe fotografie și exemplul de program. --}}
    <div class="px-3 lg:px-4">
        <section id="pentru-cine" class="scroll-mt-28 rounded-[2rem] bg-steel-50 px-5 py-16 sm:px-8 lg:p-[6.5rem]">
            <div class="mx-auto max-w-[75rem]">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                    <h2 class="max-w-[40rem] text-4xl font-semibold leading-[1.08] tracking-[-0.025em] text-ink lg:text-[2.875rem]">Orice afacere în care clienții revin</h2>
                    <p class="max-w-[26.25rem] text-[1.0625rem] leading-relaxed text-steel-600">Pornești dintr-un template și îl ajustezi. Toate regulile se pot schimba oricând, fără să pierzi ștampilele clienților.</p>
                </div>

                <ul data-anim="stagger" class="mt-12 grid gap-x-6 gap-y-10 sm:grid-cols-2 lg:mt-14 lg:grid-cols-4">
                    @foreach ($audiences as $audience)
                        <li class="group flex flex-col gap-[1.125rem]">
                            <div class="relative h-[22.5rem] overflow-hidden rounded-3xl">
                                <img src="{{ $img($audience['photo']) }}" alt="" width="600" height="800" loading="lazy" decoding="async"
                                     class="h-full w-full object-cover transition duration-500 ease-out motion-safe:group-hover:scale-[1.04]">
                                <div class="absolute inset-x-0 bottom-0 h-40 bg-gradient-to-b from-navy-950/0 to-navy-950/55" aria-hidden="true"></div>
                                <p class="absolute bottom-3.5 left-4 inline-flex items-center gap-1.5 rounded-full border border-white/30 bg-white/[0.16] px-3 py-1.5 text-xs font-semibold text-white backdrop-blur-sm transition duration-300 motion-safe:group-hover:-translate-y-1">
                                    <x-ui.icon name="clock" class="h-3.5 w-3.5" /> {{ $audience['rule'] }}
                                </p>
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <h3 class="text-[1.375rem] font-semibold leading-tight tracking-[-0.015em] text-ink">{{ $audience['title'] }}</h3>
                                <p class="text-[0.9375rem] font-semibold text-jade-700">{{ $audience['reward'] }}</p>
                                <p class="text-[0.9375rem] leading-relaxed text-steel-600">{{ $audience['text'] }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    </div>

    {{-- 5. Anti-fraudă: panoul bleumarin, patru garanții, fotografia scanării în dreapta. --}}
    <div class="px-3 pt-4 lg:px-4">
        <section id="anti-frauda" class="scroll-mt-28 rounded-[2rem] bg-navy-900 px-5 py-16 sm:px-8 lg:p-[6.5rem]">
            <div class="mx-auto flex max-w-[75rem] flex-col gap-12 lg:flex-row lg:items-center lg:gap-20">
                <div class="flex flex-1 flex-col gap-10">
                    <div class="flex flex-col gap-4">
                        <h2 class="max-w-[35rem] text-4xl font-semibold leading-[1.08] tracking-[-0.025em] text-white lg:text-[2.875rem]">Fiecare ștampilă e una adevărată</h2>
                        <p class="max-w-[32.5rem] text-[1.0625rem] leading-relaxed text-navy-200">Programul de fidelitate trebuie să aducă clienți, nu pierderi. Regulile de protecție sunt incluse, nu opționale.</p>
                    </div>
                    <ul data-anim="stagger" class="grid gap-x-8 gap-y-7 sm:grid-cols-2">
                        @foreach ($safeguards as $safeguard)
                            <li class="flex flex-col gap-3">
                                <span class="flex h-10 w-10 items-center justify-center rounded-xl border border-navy-700 bg-navy-800">
                                    <x-ui.icon :name="$safeguard['icon']" class="h-5 w-5 text-jade-300" />
                                </span>
                                <h3 class="text-[1.0625rem] font-semibold leading-[1.55] tracking-[-0.005em] text-white">{{ $safeguard['title'] }}</h3>
                                <p class="text-[0.9375rem] leading-relaxed text-navy-200">{{ $safeguard['text'] }}</p>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="mx-auto aspect-[340/522] w-full max-w-[21.25rem] overflow-hidden rounded-[1.75rem] lg:mx-0 lg:shrink-0">
                    <img src="{{ $img('secure-scan') }}" alt="Angajatul scanează codul QR de pe telefonul clientului" width="680" height="1014" loading="lazy" decoding="async"
                         class="h-full w-full object-cover">
                </div>
            </div>
        </section>
    </div>

    {{-- 6. Roluri: fotografia cu cele trei roluri în stânga, lista rolurilor în dreapta. --}}
    <section id="roluri" class="scroll-mt-28 px-5 py-20 sm:px-8 lg:px-[7.5rem] lg:py-28">
        <div class="mx-auto flex max-w-[75rem] flex-col gap-12 xl:flex-row xl:items-center xl:gap-20">
            <div class="aspect-[540/640] w-full overflow-hidden rounded-[1.75rem] xl:w-[33.75rem] xl:shrink-0">
                <img data-anim="parallax" src="{{ $img('roles') }}" alt="Angajata scanează cardul unei cliente, iar patronul urmărește programul pe tabletă" width="928" height="1152" loading="lazy" decoding="async"
                     class="h-full w-full scale-110 object-cover">
            </div>

            <div class="flex flex-1 flex-col gap-8">
                <div class="flex flex-col gap-4">
                    <p class="text-[0.8125rem] font-semibold uppercase tracking-[0.14em] text-jade-600">Trei roluri</p>
                    <h2 class="max-w-[32.5rem] text-4xl font-semibold leading-[1.08] tracking-[-0.025em] text-ink lg:text-[2.875rem]">Un singur sistem, trei roluri</h2>
                    <p class="max-w-[31.25rem] text-[1.0625rem] leading-relaxed text-steel-600">Fiecare vede doar ce are nevoie: patronul cifrele, angajatul scannerul, clientul cardul.</p>
                </div>
                <ul class="border-b border-steel-100">
                    @foreach ($roles as $role)
                        <li class="flex gap-5 border-t border-steel-100 py-6">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-jade-50">
                                <x-ui.icon :name="$role['icon']" class="h-[1.375rem] w-[1.375rem] text-jade-600" />
                            </span>
                            <div class="flex flex-col gap-1.5">
                                <h3 class="text-[1.1875rem] font-semibold leading-[1.55] tracking-[-0.01em] text-ink">{{ $role['role'] }}</h3>
                                <p class="text-[0.9375rem] text-steel-600">{{ $role['text'] }}</p>
                                <ul class="flex flex-wrap gap-2 pt-1.5">
                                    @foreach ($role['chips'] as $chip)
                                        <li class="inline-flex items-center gap-1.5 rounded-full bg-steel-50 px-2.5 py-[0.3125rem] text-xs font-medium text-steel-600">
                                            <x-ui.icon name="check-bold" class="h-[0.8125rem] w-[0.8125rem] text-jade-600" /> {{ $chip }}
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

    {{-- 7. CTA final: panoul verde cu ilustrația (Figma AI, o singură imagine), textul în stânga. --}}
    <div class="px-3 lg:px-4">
        <section class="relative isolate overflow-hidden rounded-[2rem] bg-jade-600 px-5 py-16 sm:px-8 lg:px-[6.5rem] lg:py-[5.5rem]">
            <img src="{{ $img('cta-bg') }}" alt="" aria-hidden="true" width="1584" height="672" loading="lazy" decoding="async"
                 class="pointer-events-none absolute inset-0 -z-10 h-full w-full select-none object-cover object-right max-lg:opacity-25">
            <div class="mx-auto max-w-[75rem]">
                <div class="flex max-w-[37.5rem] flex-col gap-5 lg:min-h-[21.25rem] lg:justify-center">
                    <h2 class="text-4xl font-semibold leading-[1.05] tracking-[-0.03em] text-white sm:text-5xl lg:text-[3.25rem]">Primul program e gata<br class="hidden sm:block"> în câteva minute.</h2>
                    <p class="max-w-[32.5rem] text-lg leading-[1.55] text-jade-50">Primii clienți îl folosesc chiar azi. Fără instalări, fără hardware, fără cartonașe.</p>
                    <div class="flex flex-wrap gap-3 pt-3">
                        @auth
                            <x-marketing.button :href="route('dashboard')" variant="white" arrow>Mergi la dashboard</x-marketing.button>
                        @else
                            <x-marketing.button :href="route('register')" variant="white" arrow>Creează-ți contul</x-marketing.button>
                            <x-marketing.button :href="route('login')" variant="outline">Am deja cont</x-marketing.button>
                        @endauth
                    </div>
                </div>
            </div>
        </section>
    </div>
</x-marketing-layout>
