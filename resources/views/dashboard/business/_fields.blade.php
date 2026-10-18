@php($business ??= null)
<x-ui.field label="Numele afacerii" for="name">
    <x-ui.input id="name" name="name" type="text" :value="old('name', $business?->name)" required autofocus maxlength="120" />
</x-ui.field>

<x-ui.field label="Tipul afacerii" for="type">
    <x-ui.select id="type" name="type" class="w-full" required>
        @foreach ($types as $type)
            <option value="{{ $type->value }}" @selected(old('type', $business?->type->value) === $type->value)>{{ $type->label() }}</option>
        @endforeach
    </x-ui.select>
</x-ui.field>

<x-ui.field label="Fusul orar" for="timezone">
    <x-ui.select id="timezone" name="timezone" class="w-full" required>
        @foreach ($timezones as $timezone)
            <option value="{{ $timezone }}" @selected(old('timezone', $business?->timezone ?? 'Europe/Bucharest') === $timezone)>{{ $timezone }}</option>
        @endforeach
    </x-ui.select>
</x-ui.field>

{{-- Bulinele, selectorul nativ și câmpul hex scriu aceeași valoare; câmpul hex e cel trimis (și merge fără JavaScript). --}}
<x-ui.field label="Culoarea brandului" for="brand_color" optional
            x-data="{ color: $el.dataset.color, get valid() { return /^#[0-9a-f]{6}$/i.test(this.color) } }"
            data-color="{{ old('brand_color', $business?->brand_color) }}">
    <div class="flex flex-wrap items-center gap-2">
        @foreach (\App\Support\Color::SWATCHES as $swatch)
            <button type="button" x-cloak @click="color = '{{ $swatch }}'" title="{{ $swatch }}" aria-label="Culoarea {{ $swatch }}"
                    :aria-pressed="(color.toLowerCase() === '{{ $swatch }}').toString()"
                    class="h-8 w-8 rounded-full ring-offset-2 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-focus"
                    :class="color.toLowerCase() === '{{ $swatch }}' ? 'ring-2 ring-inverse' : 'hover:ring-2 hover:ring-line-strong'"
                    style="background-color: {{ $swatch }}"></button>
        @endforeach
        <label x-cloak class="ml-1 flex items-center gap-2 text-sm text-ink-secondary">
            <input type="color" :value="valid ? color.toLowerCase() : '#01a760'" @input="color = $event.target.value" class="h-8 w-10 cursor-pointer rounded border-line-strong">
            Altă culoare
        </label>
    </div>
    <x-ui.input id="brand_color" name="brand_color" type="text" class="mt-3 max-w-40 tabular-nums" :value="old('brand_color', $business?->brand_color)"
                x-model="color" placeholder="#01A760" maxlength="7" />
</x-ui.field>
