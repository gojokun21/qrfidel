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

<x-ui.field label="Fusul orar" for="timezone" hint="Limita zilnică de ștampile se calculează după ziua din acest fus orar.">
    <x-ui.select id="timezone" name="timezone" class="w-full" required>
        @foreach ($timezones as $timezone)
            <option value="{{ $timezone }}" @selected(old('timezone', $business?->timezone ?? 'Europe/Bucharest') === $timezone)>{{ $timezone }}</option>
        @endforeach
    </x-ui.select>
</x-ui.field>

<x-ui.field label="Culoarea brandului" for="brand_color" optional hint="Cod hex, de exemplu #1F5B45. Apare pe pagina publică și pe carduri.">
    <x-ui.input id="brand_color" name="brand_color" type="text" class="max-w-40 font-mono" :value="old('brand_color', $business?->brand_color)" placeholder="#1F5B45" maxlength="7" />
</x-ui.field>
