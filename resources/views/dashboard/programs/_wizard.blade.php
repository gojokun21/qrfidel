{{--
    Wizard-ul de programe (SDD §7), comun pentru creare și editare.
    Primește: $business, $program, $icons, $templates (doar la creare), $action, $method.
--}}
@php
    $creating = ! $program->exists;
    $minStamps = ($program->cards_count ?? 0) > 0 ? $program->stamps_required : 1;
    $config = [
        'steps' => $creating ? [1, 2, 3] : [2, 3],
        'templates' => $templates ?? [],
        'selected' => old('template', $creating ? ($templates[0]['key'] ?? null) : null),
        'fallbackColor' => \App\Models\Program::fallbackColorFor($business),
        'errors' => $errors->keys(),
        'form' => [
            'name' => old('name', $program->name),
            'stamps_required' => old('stamps_required', $program->stamps_required),
            'max_stamps_per_visit' => old('max_stamps_per_visit', $program->max_stamps_per_visit),
            'cooldown_minutes' => old('cooldown_minutes', $program->cooldown_minutes),
            'daily_limit' => old('daily_limit', $program->daily_limit) ?? '',
            'stamps_expire_days' => old('stamps_expire_days', $program->stamps_expire_days) ?? '',
            'reward_expire_days' => old('reward_expire_days', $program->reward_expire_days) ?? '',
            'rollover_extra' => (bool) old('rollover_extra', $program->rollover_extra),
            'reward_title' => old('reward_title', $program->reward_title),
            'reward_description' => old('reward_description', $program->reward_description) ?? '',
            'color' => old('color', $program->color) ?? '',
            'stamp_icon' => old('stamp_icon', $program->stamp_icon->value),
            'terms' => old('terms', $program->terms) ?? '',
        ],
    ];
    $stepTitles = [1 => 'Tip și template', 2 => 'Reguli', 3 => 'Aspect'];
    $swatches = ['#CC9933', '#7c2d12', '#b91c1c', '#be185d', '#7e22ce', '#1d4ed8', '#0f766e', '#15803d'];
@endphp

<form method="POST" action="{{ $action }}" novalidate x-data="programWizard(@js($config))" @submit="submit($event)" @keydown.enter="enter($event)" x-ref="wizard" class="scroll-mt-6">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    {{-- Iconurile ștampilelor, copiate de previzualizare (`x-html`). --}}
    @foreach ($icons as $icon)
        <template x-ref="icon-{{ $icon->value }}"><x-icon :name="$icon->value" /></template>
    @endforeach

    {{-- Pașii --}}
    <ol class="mb-6 flex flex-wrap gap-2" aria-label="Pașii wizard-ului">
        @foreach ($config['steps'] as $number)
            <li>
                <button type="button" @click="go({{ $number }})"
                        class="flex items-center gap-2 rounded-full border px-4 py-2 text-sm font-medium transition"
                        :class="step === {{ $number }} ? 'border-espresso-900 bg-espresso-900 text-cream-50' : 'border-espresso-200 bg-white text-espresso-600 hover:border-espresso-400'"
                        :aria-current="step === {{ $number }} ? 'step' : null">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-gold-500 text-xs font-bold text-espresso-950">{{ $loop->iteration }}</span>
                    {{ $stepTitles[$number] }}
                </button>
            </li>
        @endforeach
    </ol>

    <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <x-card class="space-y-6">
            <x-flash />

            {{-- Pasul 1: tip și template (doar la creare) --}}
            @if ($creating)
                <section x-ref="step-1" x-show="step === 1" class="space-y-6">
                    <div>
                        <h3 class="text-lg font-semibold text-espresso-900">Alege un punct de pornire</h3>
                        <p class="mt-1 text-sm text-espresso-500">Template-ul completează regulile. Le poți schimba pe toate în pașii următori.</p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ($templates as $template)
                            <button type="button" data-template="{{ $template['key'] }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}"
                                    @click="applyTemplate('{{ $template['key'] }}')"
                                    :aria-pressed="selected === '{{ $template['key'] }}' ? 'true' : 'false'"
                                    class="flex items-start gap-3 rounded-2xl border-2 p-4 text-left transition hover:border-gold-400"
                                    :class="selected === '{{ $template['key'] }}' ? 'border-gold-500 bg-gold-50' : 'border-espresso-100 bg-white'">
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-espresso-900 text-gold-400">
                                    <x-icon :name="$template['attributes']['stamp_icon']" class="h-5 w-5" />
                                </span>
                                <span class="min-w-0">
                                    <span class="block font-semibold text-espresso-900">{{ $template['label'] }}</span>
                                    <span class="block text-sm text-espresso-500">{{ $template['summary'] }}</span>
                                </span>
                            </button>
                        @endforeach
                    </div>
                    <input type="hidden" name="template" :value="selected ?? ''">
                    <x-input-error :messages="$errors->get('template')" />

                    <div>
                        <x-input-label for="name" value="Numele programului" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" x-model="form.name" required maxlength="120" />
                        <p class="mt-1 text-sm text-espresso-500">Așa îl văd clienții, de exemplu „5 cafele = 1 gratis”.</p>
                        <x-input-error class="mt-2" :messages="$errors->get('name')" />
                    </div>
                </section>
            @endif

            {{-- Pasul 2: reguli --}}
            <section x-ref="step-2" x-show="step === 2" @if ($creating) x-cloak @endif class="space-y-6">
                <div>
                    <h3 class="text-lg font-semibold text-espresso-900">Reguli</h3>
                    <p class="mt-1 text-sm text-espresso-500">Câte ștampile, cât de des și cât sunt valabile. Regulile anti-fraudă se aplică la fiecare ștampilă.</p>
                </div>

                @unless ($creating)
                    <div>
                        <x-input-label for="name" value="Numele programului" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" x-model="form.name" required maxlength="120" />
                        <x-input-error class="mt-2" :messages="$errors->get('name')" />
                    </div>
                @endunless

                @if (($program->cards_count ?? 0) > 0)
                    <div class="rounded-2xl border border-gold-200 bg-gold-50 p-4 text-sm text-espresso-800">
                        Programul are {{ trans_choice('{1} :count card emis|[2,19] :count carduri emise|[20,*] :count de carduri emise', $program->cards_count) }}.
                        Poți mări numărul de ștampile necesare, dar nu îl poți micșora. Pentru o schimbare mare, duplică programul și arhivează-l pe acesta.
                    </div>
                @endif

                <div class="grid gap-6 sm:grid-cols-2">
                    <div>
                        <x-input-label for="stamps_required" value="Ștampile necesare" />
                        <x-text-input id="stamps_required" name="stamps_required" type="number" class="mt-1 block w-full" x-model="form.stamps_required" required min="{{ $minStamps }}" max="50" />
                        <p class="mt-1 text-sm text-espresso-500">După atâtea ștampile clientul primește recompensa.</p>
                        <x-input-error class="mt-2" :messages="$errors->get('stamps_required')" />
                    </div>

                    <div>
                        <x-input-label for="max_stamps_per_visit" value="Ștampile pe vizită (maxim)" />
                        <x-text-input id="max_stamps_per_visit" name="max_stamps_per_visit" type="number" class="mt-1 block w-full" x-model="form.max_stamps_per_visit" required min="1" ::max="form.stamps_required" />
                        <p class="mt-1 text-sm text-espresso-500">Câte poate da angajatul odată (de ex. 2 cafele cumpărate).</p>
                        <x-input-error class="mt-2" :messages="$errors->get('max_stamps_per_visit')" />
                    </div>

                    <div>
                        <x-input-label for="cooldown_minutes" value="Pauză între ștampile (minute)" />
                        <x-text-input id="cooldown_minutes" name="cooldown_minutes" type="number" class="mt-1 block w-full" x-model="form.cooldown_minutes" required min="0" max="10080" />
                        <p class="mt-1 text-sm text-espresso-500">0 = fără pauză. Oprește ștampilele puse de două ori la rând.</p>
                        <x-input-error class="mt-2" :messages="$errors->get('cooldown_minutes')" />
                    </div>

                    <div>
                        <x-input-label for="daily_limit" value="Limită zilnică de ștampile" />
                        <x-text-input id="daily_limit" name="daily_limit" type="number" class="mt-1 block w-full" x-model="form.daily_limit" ::min="form.max_stamps_per_visit" max="50" placeholder="Fără limită" />
                        <p class="mt-1 text-sm text-espresso-500">Gol = fără limită. Se numără după fusul orar al afacerii.</p>
                        <x-input-error class="mt-2" :messages="$errors->get('daily_limit')" />
                    </div>

                    <div>
                        <x-input-label for="stamps_expire_days" value="Ștampilele expiră după (zile)" />
                        <x-text-input id="stamps_expire_days" name="stamps_expire_days" type="number" class="mt-1 block w-full" x-model="form.stamps_expire_days" min="1" max="3650" placeholder="Nu expiră" />
                        <x-input-error class="mt-2" :messages="$errors->get('stamps_expire_days')" />
                    </div>

                    <div>
                        <x-input-label for="reward_expire_days" value="Recompensa expiră după (zile)" />
                        <x-text-input id="reward_expire_days" name="reward_expire_days" type="number" class="mt-1 block w-full" x-model="form.reward_expire_days" min="1" max="3650" placeholder="Nu expiră" />
                        <x-input-error class="mt-2" :messages="$errors->get('reward_expire_days')" />
                    </div>
                </div>

                <label class="flex items-start gap-3">
                    <input type="hidden" name="rollover_extra" value="0">
                    <input type="checkbox" name="rollover_extra" value="1" x-model="form.rollover_extra" class="mt-1 rounded border-espresso-300 text-gold-600 focus:ring-gold-500">
                    <span>
                        <span class="block text-sm font-medium text-espresso-800">Păstrează ștampilele în plus</span>
                        <span class="block text-sm text-espresso-500">Dacă la ultima vizită se depășește pragul, surplusul trece pe cardul următor.</span>
                    </span>
                </label>
            </section>

            {{-- Pasul 3: aspect --}}
            <section x-ref="step-3" x-show="step === 3" x-cloak class="space-y-6">
                <div>
                    <h3 class="text-lg font-semibold text-espresso-900">Aspect</h3>
                    <p class="mt-1 text-sm text-espresso-500">Ce primește clientul și cum arată cardul lui.</p>
                </div>

                <div>
                    <x-input-label for="reward_title" value="Recompensa" />
                    <x-text-input id="reward_title" name="reward_title" type="text" class="mt-1 block w-full" x-model="form.reward_title" required maxlength="120" />
                    <x-input-error class="mt-2" :messages="$errors->get('reward_title')" />
                </div>

                <div>
                    <x-input-label for="reward_description" value="Descrierea recompensei (opțional)" />
                    <textarea id="reward_description" name="reward_description" rows="2" maxlength="2000" x-model="form.reward_description"
                              class="mt-1 block w-full rounded-xl border-espresso-200 text-espresso-900 shadow-sm focus:border-gold-500 focus:ring-gold-500"></textarea>
                    <x-input-error class="mt-2" :messages="$errors->get('reward_description')" />
                </div>

                <fieldset>
                    <legend class="text-sm font-medium text-espresso-700">Culoarea cardului</legend>
                    <input type="hidden" name="color" :value="form.color">
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <button type="button" @click="form.color = ''"
                                class="rounded-full border px-3 py-1.5 text-sm transition"
                                :class="form.color === '' ? 'border-espresso-900 bg-espresso-900 text-cream-50' : 'border-espresso-200 text-espresso-600 hover:border-espresso-400'">
                            {{ $business->brand_color ? 'Culoarea brandului' : 'Implicită' }}
                        </button>
                        @foreach ($swatches as $swatch)
                            <button type="button" @click="form.color = '{{ strtolower($swatch) }}'" title="{{ $swatch }}" aria-label="Culoarea {{ $swatch }}"
                                    class="h-8 w-8 rounded-full ring-offset-2 transition"
                                    :class="form.color.toLowerCase() === '{{ strtolower($swatch) }}' ? 'ring-2 ring-espresso-900' : 'hover:ring-2 hover:ring-espresso-200'"
                                    style="background-color: {{ $swatch }}"></button>
                        @endforeach
                        <label class="ml-1 flex items-center gap-2 text-sm text-espresso-600">
                            <input type="color" :value="preview.color" @input="form.color = $event.target.value" class="h-8 w-10 cursor-pointer rounded border-espresso-200">
                            Altă culoare
                        </label>
                    </div>
                    <x-input-error class="mt-2" :messages="$errors->get('color')" />
                </fieldset>

                <fieldset>
                    <legend class="text-sm font-medium text-espresso-700">Iconul ștampilei</legend>
                    <div class="mt-2 grid grid-cols-4 gap-2 sm:grid-cols-8">
                        @foreach ($icons as $icon)
                            <label class="cursor-pointer" title="{{ $icon->label() }}">
                                <input type="radio" name="stamp_icon" value="{{ $icon->value }}" x-model="form.stamp_icon" class="peer sr-only">
                                <span class="flex aspect-square items-center justify-center rounded-xl border-2 border-espresso-100 text-espresso-700 transition peer-checked:border-gold-500 peer-checked:bg-gold-50 peer-focus-visible:ring-2 peer-focus-visible:ring-gold-500 hover:border-gold-300">
                                    <x-icon :name="$icon->value" class="h-6 w-6" />
                                    <span class="sr-only">{{ $icon->label() }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    <x-input-error class="mt-2" :messages="$errors->get('stamp_icon')" />
                </fieldset>

                <div>
                    <x-input-label for="terms" value="Termeni (opțional)" />
                    <textarea id="terms" name="terms" rows="3" maxlength="2000" x-model="form.terms" placeholder="De exemplu: recompensa nu se cumulează cu alte oferte."
                              class="mt-1 block w-full rounded-xl border-espresso-200 text-espresso-900 shadow-sm placeholder:text-espresso-300 focus:border-gold-500 focus:ring-gold-500"></textarea>
                    <x-input-error class="mt-2" :messages="$errors->get('terms')" />
                </div>
            </section>

            {{-- Navigare --}}
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-espresso-100 pt-6">
                <button type="button" class="btn-outline" x-show="stepIndex > 0" @click="back()">Înapoi</button>
                <span x-show="stepIndex === 0"></span>

                <div class="flex flex-wrap gap-3">
                    @if ($creating)
                        <button type="submit" name="intent" value="draft" class="btn-outline" x-show="isLast" :disabled="! isLast || submitting" x-cloak>Salvează ca ciornă</button>
                        <button type="submit" name="intent" value="publish" class="btn-gold" x-show="isLast" :disabled="! isLast || submitting" x-cloak>Publică programul</button>
                    @else
                        <button type="submit" class="btn-gold" x-show="isLast" :disabled="! isLast || submitting" x-cloak>Salvează</button>
                    @endif
                    <button type="button" class="btn-dark" x-show="! isLast" @click="next()">Înainte</button>
                </div>
            </div>
        </x-card>

        {{-- Previzualizare live --}}
        <aside class="lg:sticky lg:top-8 lg:self-start" aria-label="Previzualizarea cardului">
            <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-espresso-500">Previzualizare</p>
            <x-loyalty-card live
                            :business="$business->name"
                            :name="$config['form']['name']"
                            :required="(int) $config['form']['stamps_required']"
                            :reward="$config['form']['reward_title']"
                            :color="\App\Support\Color::isHex($config['form']['color']) ? $config['form']['color'] : $config['fallbackColor']"
                            :icon="$config['form']['stamp_icon']" />
            <p class="mt-3 text-sm text-espresso-500">Așa vede clientul cardul în telefon, după câteva vizite.</p>
        </aside>
    </div>
</form>
