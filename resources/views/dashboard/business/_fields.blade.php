@php($business ??= null)
<div>
    <x-input-label for="name" value="Numele afacerii" />
    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $business?->name)" required autofocus maxlength="120" />
    <x-input-error class="mt-2" :messages="$errors->get('name')" />
</div>

<div>
    <x-input-label for="type" value="Tipul afacerii" />
    <x-select-input id="type" name="type" class="mt-1 block w-full" required>
        @foreach ($types as $type)
            <option value="{{ $type->value }}" @selected(old('type', $business?->type->value) === $type->value)>{{ $type->label() }}</option>
        @endforeach
    </x-select-input>
    <x-input-error class="mt-2" :messages="$errors->get('type')" />
</div>

<div>
    <x-input-label for="timezone" value="Fusul orar" />
    <x-select-input id="timezone" name="timezone" class="mt-1 block w-full" required>
        @foreach ($timezones as $timezone)
            <option value="{{ $timezone }}" @selected(old('timezone', $business?->timezone ?? 'Europe/Bucharest') === $timezone)>{{ $timezone }}</option>
        @endforeach
    </x-select-input>
    <p class="mt-1 text-sm text-gray-500">Limita zilnică de ștampile se calculează după ziua din acest fus orar.</p>
    <x-input-error class="mt-2" :messages="$errors->get('timezone')" />
</div>

<div>
    <x-input-label for="brand_color" value="Culoarea brandului (opțional)" />
    <x-text-input id="brand_color" name="brand_color" type="text" class="mt-1 block w-40 font-mono" :value="old('brand_color', $business?->brand_color)" placeholder="#4f46e5" maxlength="7" />
    <x-input-error class="mt-2" :messages="$errors->get('brand_color')" />
</div>
