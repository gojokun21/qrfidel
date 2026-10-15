<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Echipa</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <x-flash />

            <x-card class="p-0 overflow-hidden">
                <h3 class="px-6 pt-6 text-lg font-medium text-gray-900">Membri</h3>

                @foreach ($memberships as $membership)
                    <div class="flex flex-col gap-3 p-6 lg:flex-row lg:items-center lg:justify-between {{ $loop->first ? '' : 'border-t border-gray-100' }}">
                        <div>
                            <p class="font-medium text-gray-900">
                                {{ $membership->user->name }}
                                @if ($membership->user_id === auth()->id())
                                    <span class="text-sm text-gray-500">(tu)</span>
                                @endif
                            </p>
                            <p class="text-sm text-gray-500">{{ $membership->user->email }}</p>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <form method="POST" action="{{ route('dashboard.team.update', $membership->id) }}" class="flex flex-wrap items-center gap-2"
                                  x-data="{ role: @js($membership->role->value) }">
                                @csrf
                                @method('PATCH')
                                <x-select-input name="role" x-model="role" class="text-sm" aria-label="Rol">
                                    @foreach ($roles as $role)
                                        <option value="{{ $role->value }}" @selected($membership->role === $role)>{{ $role->label() }}</option>
                                    @endforeach
                                </x-select-input>
                                <x-select-input name="location_id" class="text-sm" aria-label="Locație" x-bind:disabled="role === 'owner'">
                                    <option value="">Toate locațiile</option>
                                    @foreach ($locations as $location)
                                        <option value="{{ $location->id }}" @selected($membership->location_id === $location->id)>{{ $location->name }}</option>
                                    @endforeach
                                </x-select-input>
                                <x-secondary-button type="submit">Salvează</x-secondary-button>
                            </form>

                            <form method="POST" action="{{ route('dashboard.team.destroy', $membership->id) }}"
                                  x-data @submit="if (! confirm('Scoți acest membru din echipă? Istoricul lui rămâne în jurnal.')) $event.preventDefault()">
                                @csrf
                                @method('DELETE')
                                <button class="text-sm text-red-600 hover:underline">Scoate din echipă</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </x-card>

            <x-card>
                <h3 class="text-lg font-medium text-gray-900">Invită un membru</h3>
                <p class="mt-1 text-sm text-gray-600">Persoana primește un email cu un link valabil {{ \App\Services\Loyalty\InvitationService::VALID_DAYS }} zile.</p>

                <form method="POST" action="{{ route('dashboard.invitations.store') }}" class="mt-4 grid gap-4 sm:grid-cols-4 sm:items-end"
                      x-data="{ role: @js(old('role', 'staff')) }">
                    @csrf
                    <div class="sm:col-span-2">
                        <x-input-label for="invite_email" value="Email" />
                        <x-text-input id="invite_email" name="email" type="email" class="mt-1 block w-full" :value="old('email')" required />
                    </div>
                    <div>
                        <x-input-label for="invite_role" value="Rol" />
                        <x-select-input id="invite_role" name="role" x-model="role" class="mt-1 block w-full">
                            @foreach ($roles as $role)
                                <option value="{{ $role->value }}">{{ $role->label() }}</option>
                            @endforeach
                        </x-select-input>
                    </div>
                    <div>
                        <x-input-label for="invite_location" value="Locație" />
                        <x-select-input id="invite_location" name="location_id" class="mt-1 block w-full" x-bind:disabled="role === 'owner'">
                            <option value="">Toate locațiile</option>
                            @foreach ($locations as $location)
                                <option value="{{ $location->id }}" @selected((string) old('location_id') === (string) $location->id)>{{ $location->name }}</option>
                            @endforeach
                        </x-select-input>
                    </div>
                    <div class="sm:col-span-4">
                        <x-input-error :messages="$errors->get('email')" />
                        <x-input-error :messages="$errors->get('role')" />
                        <x-input-error :messages="$errors->get('location_id')" />
                        <x-primary-button class="mt-2">Trimite invitația</x-primary-button>
                    </div>
                </form>
            </x-card>

            @if ($invitations->isNotEmpty())
                <x-card class="p-0 overflow-hidden">
                    <h3 class="px-6 pt-6 text-lg font-medium text-gray-900">Invitații trimise</h3>

                    @foreach ($invitations as $invitation)
                        <div class="flex flex-col gap-2 p-6 sm:flex-row sm:items-center sm:justify-between {{ $loop->first ? '' : 'border-t border-gray-100' }}">
                            <div>
                                <p class="font-medium text-gray-900">{{ $invitation->email }}</p>
                                <p class="text-sm text-gray-500">
                                    {{ $invitation->role->label() }}{{ $invitation->location ? ' · '.$invitation->location->name : '' }} ·
                                    @if ($invitation->isExpired())
                                        <span class="text-amber-600">expirată – trimite din nou</span>
                                    @else
                                        expiră pe {{ $invitation->expires_at->timezone($business->timezone)->format('d.m.Y H:i') }}
                                    @endif
                                </p>
                            </div>
                            <form method="POST" action="{{ route('dashboard.invitations.destroy', $invitation) }}">
                                @csrf
                                @method('DELETE')
                                <button class="text-sm text-red-600 hover:underline">Anulează</button>
                            </form>
                        </div>
                    @endforeach
                </x-card>
            @endif
        </div>
    </div>
</x-app-layout>
