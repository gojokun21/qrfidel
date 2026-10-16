{{-- Locația angajatului (ADR 011): fixă pentru membrii legați de o locație, altfel aleasă o dată (sesiune). Folosită de scanare și de panoul de cereri. --}}
@if ($locations->count() > 1)
    <form method="POST" action="{{ route('staff.location') }}" class="flex items-end gap-3" x-data
          @change="$el.requestSubmit()">
        @csrf
        <x-ui.field :label="$location ? 'Locația ta' : 'Alege locația în care lucrezi'" for="location_id" class="flex-1">
            <x-ui.select id="location_id" name="location_id" class="w-full" required>
                @unless ($location)
                    <option value="" selected disabled>Alege locația…</option>
                @endunless
                @foreach ($locations as $option)
                    <option value="{{ $option->id }}" @selected($location?->is($option))>{{ $option->name }}</option>
                @endforeach
            </x-ui.select>
        </x-ui.field>
        <noscript><x-ui.button variant="secondary">Salvează</x-ui.button></noscript>
    </form>
@else
    <p class="flex items-center gap-2 text-sm text-ink-secondary">
        <x-ui.icon name="map-pin" class="h-4 w-4 text-primary-ink" />
        Lucrezi la <span class="font-semibold text-ink">{{ $location?->name }}</span>
    </p>
    <x-ui.error :messages="$errors->get('location_id')" />
@endif
