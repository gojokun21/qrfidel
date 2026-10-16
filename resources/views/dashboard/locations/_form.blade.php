<x-ui.field label="Nume" for="name">
    <x-ui.input id="name" name="name" type="text" :value="old('name', $location->name)" required autofocus maxlength="120" />
</x-ui.field>

<x-ui.field label="Adresă" for="address" optional>
    <x-ui.input id="address" name="address" type="text" :value="old('address', $location->address)" maxlength="255" />
</x-ui.field>

<div class="flex items-center gap-3">
    <x-ui.button>Salvează</x-ui.button>
    <x-ui.button :href="route('dashboard.locations.index')" variant="ghost">Renunță</x-ui.button>
</div>
