{{-- Cifrele unui BusinessReport (ADR 014); folosit în „Rapoarte” și în detaliul afacerii din admin. --}}
@php
    $periodLabel = $report->periodLabel();
@endphp

<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    <x-ui.stat label="Clienți activi" :value="$report->activeCustomers" :hint="$periodLabel" icon="users" />
    <x-ui.stat label="Ștampile azi" :value="$report->stampsToday" hint="De la miezul nopții" icon="clock" />
    <x-ui.stat label="Ștampile în 7 zile" :value="$report->stampsWeek" hint="Inclusiv azi" icon="star" />
    <x-ui.stat label="Ștampile" :value="$report->stamps" :hint="$periodLabel" icon="chart" />
    <x-ui.stat label="Recompense emise" :value="$report->rewardsIssued" :hint="$periodLabel" icon="gift" />
    <x-ui.stat label="Recompense folosite" :value="$report->rewardsRedeemed" :hint="$periodLabel" icon="heart" />
</div>

<div class="grid gap-6 lg:grid-cols-2">
    <x-ui.card flush title="Top locații">
        <x-ui.table>
            <x-slot:head>
                <x-ui.table.th>Locația</x-ui.table.th>
                <x-ui.table.th numeric>Ștampile</x-ui.table.th>
                <x-ui.table.th numeric>Recompense folosite</x-ui.table.th>
            </x-slot:head>
            @forelse ($report->locations as $location)
                <tr>
                    <x-ui.table.td class="font-medium text-ink">{{ $location['name'] }}</x-ui.table.td>
                    <x-ui.table.td numeric>{{ $location['stamps'] }}</x-ui.table.td>
                    <x-ui.table.td numeric>{{ $location['redeems'] }}</x-ui.table.td>
                </tr>
            @empty
                <tr><x-ui.table.td colspan="3" class="text-ink-muted">Nicio locație.</x-ui.table.td></tr>
            @endforelse
        </x-ui.table>
    </x-ui.card>

    <x-ui.card flush title="Activitate per angajat">
        <x-ui.table>
            <x-slot:head>
                <x-ui.table.th>Angajat</x-ui.table.th>
                <x-ui.table.th numeric>Ștampile</x-ui.table.th>
                <x-ui.table.th numeric>Folosiri</x-ui.table.th>
                <x-ui.table.th numeric>Anulări</x-ui.table.th>
            </x-slot:head>
            @forelse ($report->staff as $member)
                <tr>
                    <x-ui.table.td class="font-medium text-ink">{{ $member['name'] }}</x-ui.table.td>
                    <x-ui.table.td numeric>{{ $member['stamps'] }}</x-ui.table.td>
                    <x-ui.table.td numeric>{{ $member['redeems'] }}</x-ui.table.td>
                    <x-ui.table.td numeric>{{ $member['voids'] }}</x-ui.table.td>
                </tr>
            @empty
                <tr><x-ui.table.td colspan="4" class="text-ink-muted">Nicio activitate în perioadă.</x-ui.table.td></tr>
            @endforelse
        </x-ui.table>
    </x-ui.card>
</div>
