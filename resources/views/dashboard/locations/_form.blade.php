<div>
    <x-input-label for="name" value="Nume" />
    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $location->name)" required autofocus maxlength="120" />
    <x-input-error class="mt-2" :messages="$errors->get('name')" />
</div>

<div>
    <x-input-label for="address" value="Adresă (opțional)" />
    <x-text-input id="address" name="address" type="text" class="mt-1 block w-full" :value="old('address', $location->address)" maxlength="255" />
    <x-input-error class="mt-2" :messages="$errors->get('address')" />
</div>

<div class="flex items-center gap-4">
    <x-primary-button>Salvează</x-primary-button>
    <a href="{{ route('dashboard.locations.index') }}" class="text-sm text-gray-600 hover:underline">Renunță</a>
</div>
