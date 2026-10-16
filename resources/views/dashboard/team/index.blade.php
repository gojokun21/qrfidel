<x-app-layout title="Echipa">
    <x-ui.card flush title="Membri">
        <ul class="divide-y divide-line">
            @foreach ($memberships as $membership)
                <li class="flex flex-col gap-3 p-5 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex min-w-0 items-center gap-4">
                        <x-ui.avatar :name="$membership->user->name" />
                        <div class="min-w-0">
                            <p class="font-semibold text-ink">
                                {{ $membership->user->name }}
                                @if ($membership->user_id === auth()->id())
                                    <span class="text-sm font-normal text-ink-muted">(tu)</span>
                                @endif
                            </p>
                            <p class="truncate text-sm text-ink-muted">{{ $membership->user->email }}</p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <form method="POST" action="{{ route('dashboard.team.update', $membership->id) }}" class="flex flex-wrap items-center gap-2"
                              x-data="{ role: @js($membership->role->value) }">
                            @csrf
                            @method('PATCH')
                            <x-ui.select name="role" x-model="role" class="text-sm" aria-label="Rolul lui {{ $membership->user->name }}">
                                @foreach ($roles as $role)
                                    <option value="{{ $role->value }}" @selected($membership->role === $role)>{{ $role->label() }}</option>
                                @endforeach
                            </x-ui.select>
                            <x-ui.select name="location_id" class="text-sm" aria-label="Locația lui {{ $membership->user->name }}" x-bind:disabled="role === 'owner'">
                                <option value="">Toate locațiile</option>
                                @foreach ($locations as $location)
                                    <option value="{{ $location->id }}" @selected($membership->location_id === $location->id)>{{ $location->name }}</option>
                                @endforeach
                            </x-ui.select>
                            <x-ui.button variant="secondary" size="sm">Salvează</x-ui.button>
                        </form>

                        <form method="POST" action="{{ route('dashboard.team.destroy', $membership->id) }}"
                              x-data @submit="if (! confirm('Scoți acest membru din echipă? Istoricul lui rămâne în jurnal.')) $event.preventDefault()">
                            @csrf
                            @method('DELETE')
                            <x-ui.button variant="danger-ghost" size="sm">Scoate din echipă</x-ui.button>
                        </form>
                    </div>
                </li>
            @endforeach
        </ul>
    </x-ui.card>

    <x-ui.card title="Invită un membru" :description="'Persoana primește un email cu un link valabil '.\App\Services\Loyalty\InvitationService::VALID_DAYS.' zile.'">
        <form method="POST" action="{{ route('dashboard.invitations.store') }}" class="grid gap-4 sm:grid-cols-4 sm:items-start"
              x-data="{ role: @js(old('role', 'staff')) }">
            @csrf
            <x-ui.field label="Email" for="invite_email" error="email" class="sm:col-span-2">
                <x-ui.input id="invite_email" name="email" type="email" :value="old('email')" required autocomplete="off" />
            </x-ui.field>
            <x-ui.field label="Rol" for="invite_role" error="role">
                <x-ui.select id="invite_role" name="role" x-model="role" class="w-full">
                    @foreach ($roles as $role)
                        <option value="{{ $role->value }}">{{ $role->label() }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>
            <x-ui.field label="Locație" for="invite_location" error="location_id">
                <x-ui.select id="invite_location" name="location_id" class="w-full" x-bind:disabled="role === 'owner'">
                    <option value="">Toate locațiile</option>
                    @foreach ($locations as $location)
                        <option value="{{ $location->id }}" @selected((string) old('location_id') === (string) $location->id)>{{ $location->name }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>
            <div class="sm:col-span-4">
                <x-ui.button icon="mail">Trimite invitația</x-ui.button>
            </div>
        </form>
    </x-ui.card>

    @if ($invitations->isNotEmpty())
        <x-ui.card flush title="Invitații trimise">
            <ul class="divide-y divide-line">
                @foreach ($invitations as $invitation)
                    <li class="flex flex-col gap-2 p-5 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <p class="break-all font-medium text-ink">{{ $invitation->email }}</p>
                            <p class="flex flex-wrap items-center gap-x-1.5 gap-y-1 text-sm text-ink-muted">
                                {{ $invitation->role->label() }}{{ $invitation->location ? ' · '.$invitation->location->name : '' }} ·
                                @if ($invitation->isExpired())
                                    <x-ui.badge tone="warning">expirată – trimite din nou</x-ui.badge>
                                @else
                                    expiră pe {{ $invitation->expires_at->timezone($business->timezone)->format('d.m.Y H:i') }}
                                @endif
                            </p>
                        </div>
                        <form method="POST" action="{{ route('dashboard.invitations.destroy', $invitation) }}" class="-ml-2.5 sm:ml-0">
                            @csrf
                            @method('DELETE')
                            <x-ui.button variant="danger-ghost" size="sm">Anulează</x-ui.button>
                        </form>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>
    @endif
</x-app-layout>
