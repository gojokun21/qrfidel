{{-- Cifrele unui BusinessReport (ADR 014); folosit în „Rapoarte” și în detaliul afacerii din admin. --}}
@php
    $periodLabel = $report->periodLabel();
    $tiles = [
        ['label' => 'Clienți activi', 'value' => $report->activeCustomers, 'hint' => $periodLabel, 'icon' => 'users'],
        ['label' => 'Ștampile azi', 'value' => $report->stampsToday, 'hint' => 'De la miezul nopții', 'icon' => 'clock'],
        ['label' => 'Ștampile în 7 zile', 'value' => $report->stampsWeek, 'hint' => 'Inclusiv azi', 'icon' => 'star'],
        ['label' => 'Ștampile', 'value' => $report->stamps, 'hint' => $periodLabel, 'icon' => 'chart'],
        ['label' => 'Recompense emise', 'value' => $report->rewardsIssued, 'hint' => $periodLabel, 'icon' => 'gift'],
        ['label' => 'Recompense folosite', 'value' => $report->rewardsRedeemed, 'hint' => $periodLabel, 'icon' => 'heart'],
    ];
@endphp

<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    @foreach ($tiles as $tile)
        <x-card class="flex flex-col">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-espresso-500">{{ $tile['label'] }}</p>
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-gold-50 text-gold-600"><x-icon :name="$tile['icon']" class="h-5 w-5" /></span>
            </div>
            <p class="mt-3 text-4xl font-bold tracking-tight text-espresso-900">{{ $tile['value'] }}</p>
            <p class="mt-1 text-xs text-espresso-500">{{ $tile['hint'] }}</p>
        </x-card>
    @endforeach
</div>

<div class="grid gap-6 lg:grid-cols-2">
    <section>
        <h3 class="mb-3 text-lg font-semibold text-espresso-900">Top locații</h3>
        <x-card flush>
            <table class="w-full text-sm">
                <thead class="text-left text-xs uppercase tracking-wide text-espresso-500">
                    <tr>
                        <th class="px-5 py-3 font-medium">Locația</th>
                        <th class="px-5 py-3 text-right font-medium">Ștampile</th>
                        <th class="px-5 py-3 text-right font-medium">Recompense folosite</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($report->locations as $location)
                        <tr class="border-t border-espresso-100">
                            <td class="px-5 py-3 font-medium text-espresso-900">{{ $location['name'] }}</td>
                            <td class="px-5 py-3 text-right tabular-nums">{{ $location['stamps'] }}</td>
                            <td class="px-5 py-3 text-right tabular-nums">{{ $location['redeems'] }}</td>
                        </tr>
                    @empty
                        <tr class="border-t border-espresso-100"><td colspan="3" class="px-5 py-4 text-espresso-500">Nicio locație.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-card>
    </section>

    <section>
        <h3 class="mb-3 text-lg font-semibold text-espresso-900">Activitate per angajat</h3>
        <x-card flush>
            <table class="w-full text-sm">
                <thead class="text-left text-xs uppercase tracking-wide text-espresso-500">
                    <tr>
                        <th class="px-5 py-3 font-medium">Angajat</th>
                        <th class="px-5 py-3 text-right font-medium">Ștampile</th>
                        <th class="px-5 py-3 text-right font-medium">Folosiri</th>
                        <th class="px-5 py-3 text-right font-medium">Anulări</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($report->staff as $member)
                        <tr class="border-t border-espresso-100">
                            <td class="px-5 py-3 font-medium text-espresso-900">{{ $member['name'] }}</td>
                            <td class="px-5 py-3 text-right tabular-nums">{{ $member['stamps'] }}</td>
                            <td class="px-5 py-3 text-right tabular-nums">{{ $member['redeems'] }}</td>
                            <td class="px-5 py-3 text-right tabular-nums">{{ $member['voids'] }}</td>
                        </tr>
                    @empty
                        <tr class="border-t border-espresso-100"><td colspan="4" class="px-5 py-4 text-espresso-500">Nicio activitate în perioadă.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-card>
    </section>
</div>
