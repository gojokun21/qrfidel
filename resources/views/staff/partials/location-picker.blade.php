{{-- Locația angajatului (ADR 011): fixă pentru membrii legați de o locație, altfel aleasă o dată (sesiune). Folosită de scanare și de panoul de cereri. --}}
@if ($locations->count() > 1)
    <form method="POST" action="{{ route('staff.location') }}" class="flex items-end gap-3" x-data
          @change="$el.requestSubmit()">
        @csrf
        <div class="flex-1">
            <x-input-label for="location_id" :value="$location ? 'Locația ta' : 'Alege locația în care lucrezi'" />
            <x-select-input id="location_id" name="location_id" class="mt-1 block w-full" required>
                @unless ($location)
                    <option value="" selected disabled>Alege locația…</option>
                @endunless
                @foreach ($locations as $option)
                    <option value="{{ $option->id }}" @selected($location?->is($option))>{{ $option->name }}</option>
                @endforeach
            </x-select-input>
            <x-input-error :messages="$errors->get('location_id')" class="mt-2" />
        </div>
        <noscript><button class="btn-outline">Salvează</button></noscript>
    </form>
@else
    <p class="flex items-center gap-2 text-sm text-espresso-600">
        <x-icon name="map-pin" class="h-4 w-4 text-gold-600" />
        Lucrezi la <span class="font-semibold text-espresso-900">{{ $location?->name }}</span>
    </p>
    <x-input-error :messages="$errors->get('location_id')" />
@endif
