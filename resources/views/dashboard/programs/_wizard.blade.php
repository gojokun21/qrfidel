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
    // Culorile propuse pentru card (date de conținut, nu tokenuri ale interfeței).
    $swatches = ['#01A760', '#0891b2', '#7c2d12', '#b91c1c', '#be185d', '#7e22ce', '#1d4ed8', '#0f766e'];
@endphp

<form method="POST" action="{{ $action }}" novalidate x-data="programWizard(@js($config))" @submit="submit($event)" @keydown.enter="enter($event)" x-ref="wizard" class="scroll-mt-6">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    {{-- Iconurile ștampilelor, copiate de previzualizare (`x-html`). --}}
    @foreach ($icons as $icon)
        <template x-ref="icon-{{ $icon->value }}"><x-ui.icon :name="$icon->value" /></template>
    @endforeach

    {{-- Pașii --}}
    <ol class="mb-6 flex flex-wrap gap-2" aria-label="Pașii wizard-ului">
        @foreach ($config['steps'] as $number)
            <li>
                <button type="button" @click="go({{ $number }})"
                        class="flex items-center gap-2 rounded-full border px-4 py-2 text-sm font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2"
                        :class="step === {{ $number }} ? 'border-inverse bg-inverse text-inverse-fg' : 'border-line-strong bg-surface text-ink-secondary hover:border-ink-muted'"
                        :aria-current="step === {{ $number }} ? 'step' : null">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-fg">{{ $loop->iteration }}</span>
                    {{ $stepTitles[$number] }}
                </button>
            </li>
        @endforeach
    </ol>

    <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <x-ui.card class="space-y-6">
            {{-- Pasul 1: tip și template (doar la creare) --}}
            @if ($creating)
                <section x-ref="step-1" x-show="step === 1" class="space-y-6" aria-labelledby="step-1-title">
                    <div>
                        <h2 id="step-1-title" class="text-lg font-semibold text-ink">Alege un punct de pornire</h2>
                        <p class="mt-1 text-sm text-ink-muted">Template-ul completează regulile. Le poți schimba pe toate în pașii următori.</p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ($templates as $template)
                            <button type="button" data-template="{{ $template['key'] }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}"
                                    @click="applyTemplate('{{ $template['key'] }}')"
                                    :aria-pressed="selected === '{{ $template['key'] }}' ? 'true' : 'false'"
                                    class="flex items-start gap-3 rounded-card border-2 p-4 text-left transition hover:border-primary-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2"
                                    :class="selected === '{{ $template['key'] }}' ? 'border-primary bg-primary-soft' : 'border-line bg-surface'">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-control bg-inverse text-accent-hover">
                                    <x-ui.icon :name="$template['attributes']['stamp_icon']" class="h-5 w-5" />
                                </span>
                                <span class="min-w-0">
                                    <span class="block font-semibold text-ink">{{ $template['label'] }}</span>
                                    <span class="block text-sm text-ink-muted">{{ $template['summary'] }}</span>
                                </span>
                            </button>
                        @endforeach
                    </div>
                    <input type="hidden" name="template" :value="selected ?? ''">
                    <x-ui.error :messages="$errors->get('template')" />

                    <x-ui.field label="Numele programului" for="name" hint="Așa îl văd clienții, de exemplu „5 cafele = 1 gratis”.">
                        <x-ui.input id="name" name="name" type="text" x-model="form.name" required maxlength="120" />
                    </x-ui.field>
                </section>
            @endif

            {{-- Pasul 2: reguli --}}
            <section x-ref="step-2" x-show="step === 2" @if ($creating) x-cloak @endif class="space-y-6" aria-labelledby="step-2-title">
                <div>
                    <h2 id="step-2-title" class="text-lg font-semibold text-ink">Reguli</h2>
                    <p class="mt-1 text-sm text-ink-muted">Câte ștampile, cât de des și cât sunt valabile. Regulile anti-fraudă se aplică la fiecare ștampilă.</p>
                </div>

                @unless ($creating)
                    <x-ui.field label="Numele programului" for="name">
                        <x-ui.input id="name" name="name" type="text" x-model="form.name" required maxlength="120" />
                    </x-ui.field>
                @endunless

                @if (($program->cards_count ?? 0) > 0)
                    <x-ui.alert tone="warning">
                        Programul are {{ trans_choice('{1} :count card emis|[2,19] :count carduri emise|[20,*] :count de carduri emise', $program->cards_count) }}.
                        Poți mări numărul de ștampile necesare, dar nu îl poți micșora. Pentru o schimbare mare, duplică programul și arhivează-l pe acesta.
                    </x-ui.alert>
                @endif

                <div class="grid gap-6 sm:grid-cols-2">
                    <x-ui.field label="Ștampile necesare" for="stamps_required" hint="După atâtea ștampile clientul primește recompensa.">
                        <x-ui.input id="stamps_required" name="stamps_required" type="number" x-model="form.stamps_required" required min="{{ $minStamps }}" max="50" />
                    </x-ui.field>

                    <x-ui.field label="Ștampile pe vizită (maxim)" for="max_stamps_per_visit" hint="Câte poate da angajatul odată (de ex. 2 cafele cumpărate).">
                        <x-ui.input id="max_stamps_per_visit" name="max_stamps_per_visit" type="number" x-model="form.max_stamps_per_visit" required min="1" ::max="form.stamps_required" />
                    </x-ui.field>

                    <x-ui.field label="Pauză între ștampile (minute)" for="cooldown_minutes" hint="0 = fără pauză. Oprește ștampilele puse de două ori la rând.">
                        <x-ui.input id="cooldown_minutes" name="cooldown_minutes" type="number" x-model="form.cooldown_minutes" required min="0" max="10080" />
                    </x-ui.field>

                    <x-ui.field label="Limită zilnică de ștampile" for="daily_limit" hint="Gol = fără limită. Se numără după fusul orar al afacerii.">
                        <x-ui.input id="daily_limit" name="daily_limit" type="number" x-model="form.daily_limit" ::min="form.max_stamps_per_visit" max="50" placeholder="Fără limită" />
                    </x-ui.field>

                    <x-ui.field label="Ștampilele expiră după (zile)" for="stamps_expire_days" hint="Gol = nu expiră. Se numără de la ultima ștampilă.">
                        <x-ui.input id="stamps_expire_days" name="stamps_expire_days" type="number" x-model="form.stamps_expire_days" min="1" max="3650" placeholder="Nu expiră" />
                    </x-ui.field>

                    <x-ui.field label="Recompensa expiră după (zile)" for="reward_expire_days" hint="Gol = nu expiră.">
                        <x-ui.input id="reward_expire_days" name="reward_expire_days" type="number" x-model="form.reward_expire_days" min="1" max="3650" placeholder="Nu expiră" />
                    </x-ui.field>
                </div>

                <div>
                    <input type="hidden" name="rollover_extra" value="0">
                    <x-ui.checkbox name="rollover_extra" value="1" x-model="form.rollover_extra"
                                   label="Păstrează ștampilele în plus"
                                   description="Dacă la ultima vizită se depășește pragul, surplusul trece pe cardul următor." />
                    <x-ui.error :messages="$errors->get('rollover_extra')" class="mt-2" />
                </div>
            </section>

            {{-- Pasul 3: aspect --}}
            <section x-ref="step-3" x-show="step === 3" x-cloak class="space-y-6" aria-labelledby="step-3-title">
                <div>
                    <h2 id="step-3-title" class="text-lg font-semibold text-ink">Aspect</h2>
                    <p class="mt-1 text-sm text-ink-muted">Ce primește clientul și cum arată cardul lui.</p>
                </div>

                <x-ui.field label="Recompensa" for="reward_title">
                    <x-ui.input id="reward_title" name="reward_title" type="text" x-model="form.reward_title" required maxlength="120" />
                </x-ui.field>

                <x-ui.field label="Descrierea recompensei" for="reward_description" optional>
                    <x-ui.textarea id="reward_description" name="reward_description" rows="2" maxlength="2000" x-model="form.reward_description" />
                </x-ui.field>

                <fieldset>
                    <legend class="text-sm font-medium text-ink">Culoarea cardului</legend>
                    <input type="hidden" name="color" :value="form.color">
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <button type="button" @click="form.color = ''" :aria-pressed="(form.color === '').toString()"
                                class="rounded-full border px-3 py-1.5 text-sm transition focus:outline-none focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2"
                                :class="form.color === '' ? 'border-inverse bg-inverse text-inverse-fg' : 'border-line-strong text-ink-secondary hover:border-ink-muted'">
                            {{ $business->brand_color ? 'Culoarea brandului' : 'Implicită' }}
                        </button>
                        @foreach ($swatches as $swatch)
                            <button type="button" @click="form.color = '{{ strtolower($swatch) }}'" title="{{ $swatch }}" aria-label="Culoarea {{ $swatch }}"
                                    :aria-pressed="(form.color.toLowerCase() === '{{ strtolower($swatch) }}').toString()"
                                    class="h-8 w-8 rounded-full ring-offset-2 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-focus"
                                    :class="form.color.toLowerCase() === '{{ strtolower($swatch) }}' ? 'ring-2 ring-inverse' : 'hover:ring-2 hover:ring-line-strong'"
                                    style="background-color: {{ $swatch }}"></button>
                        @endforeach
                        <label class="ml-1 flex items-center gap-2 text-sm text-ink-secondary">
                            <input type="color" :value="preview.color" @input="form.color = $event.target.value" class="h-8 w-10 cursor-pointer rounded border-line-strong">
                            Altă culoare
                        </label>
                    </div>
                    <x-ui.error :messages="$errors->get('color')" class="mt-2" />
                </fieldset>

                <fieldset>
                    <legend class="text-sm font-medium text-ink">Iconul ștampilei</legend>
                    <div class="mt-2 grid grid-cols-4 gap-2 sm:grid-cols-8">
                        @foreach ($icons as $icon)
                            <label class="cursor-pointer" title="{{ $icon->label() }}">
                                <input type="radio" name="stamp_icon" value="{{ $icon->value }}" x-model="form.stamp_icon" class="peer sr-only">
                                <span class="flex aspect-square items-center justify-center rounded-control border-2 border-line text-ink-secondary transition hover:border-primary-line peer-checked:border-primary peer-checked:bg-primary-soft peer-checked:text-ink peer-focus-visible:ring-2 peer-focus-visible:ring-focus">
                                    <x-ui.icon :name="$icon->value" class="h-6 w-6" />
                                    <span class="sr-only">{{ $icon->label() }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    <x-ui.error :messages="$errors->get('stamp_icon')" class="mt-2" />
                </fieldset>

                <x-ui.field label="Termeni" for="terms" optional>
                    <x-ui.textarea id="terms" name="terms" rows="3" maxlength="2000" x-model="form.terms" placeholder="De exemplu: recompensa nu se cumulează cu alte oferte." />
                </x-ui.field>
            </section>

            {{-- Navigare --}}
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line pt-6">
                <x-ui.button type="button" variant="secondary" x-show="stepIndex > 0" @click="back()">Înapoi</x-ui.button>
                <span x-show="stepIndex === 0"></span>

                <div class="flex flex-wrap gap-3">
                    @if ($creating)
                        <x-ui.button name="intent" value="draft" variant="secondary" x-show="isLast" ::disabled="! isLast || submitting" x-cloak>Salvează ca ciornă</x-ui.button>
                        <x-ui.button name="intent" value="publish" x-show="isLast" ::disabled="! isLast || submitting" x-cloak>Publică programul</x-ui.button>
                    @else
                        <x-ui.button x-show="isLast" ::disabled="! isLast || submitting" x-cloak>Salvează</x-ui.button>
                    @endif
                    <x-ui.button type="button" variant="neutral" x-show="! isLast" @click="next()">Înainte</x-ui.button>
                </div>
            </div>
        </x-ui.card>

        {{-- Previzualizare live --}}
        <aside class="lg:sticky lg:top-8 lg:self-start" aria-label="Previzualizarea cardului">
            <p class="mb-3 text-xs font-semibold uppercase tracking-eyebrow text-ink-muted">Previzualizare</p>
            <x-loyalty.card live
                            :business="$business->name"
                            :name="$config['form']['name']"
                            :required="(int) $config['form']['stamps_required']"
                            :reward="$config['form']['reward_title']"
                            :color="\App\Support\Color::isHex($config['form']['color']) ? $config['form']['color'] : $config['fallbackColor']"
                            :icon="$config['form']['stamp_icon']" />
            <p class="mt-3 text-sm text-ink-muted">Așa vede clientul cardul în telefon, după câteva vizite.</p>
        </aside>
    </div>
</form>
